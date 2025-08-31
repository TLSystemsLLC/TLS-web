<?php
declare(strict_types=1);

/**
 * Menu Manager
 * Handles menu structure and security-based visibility
 * 
 * @author Tony Lyle
 * @version 1.0
 */
class MenuManager
{
    private array $menuConfig;
    private array $userMenus;
    private Auth $auth;

    /**
     * Constructor
     * 
     * @param Auth $auth Authentication instance
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        $this->menuConfig = include __DIR__ . '/../config/menus.php';
        $this->userMenus = [];
        
        // Load user menus from session if logged in
        if ($auth->isLoggedIn()) {
            $user = $auth->getCurrentUser();
            $this->userMenus = $user['menus'] ?? [];
        }
    }

    /**
     * Generate main navigation menu HTML
     * 
     * @return string Menu HTML
     */
    public function generateMainMenu(): string
    {
        if (!$this->auth->isLoggedIn()) {
            return '';
        }

        $html = '<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">';
        $html .= '<div class="container-fluid">';
        
        // Brand
        $user = $this->auth->getCurrentUser();
        $html .= '<a class="navbar-brand" href="/dashboard.php">';
        $html .= '<i class="bi bi-truck me-2"></i>' . htmlspecialchars(Config::get('APP_NAME', 'TLS Operations'));
        $html .= '</a>';
        
        // Toggle button for mobile
        $html .= '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">';
        $html .= '<span class="navbar-toggler-icon"></span>';
        $html .= '</button>';
        
        // Menu items
        $html .= '<div class="collapse navbar-collapse" id="navbarNav">';
        $html .= '<ul class="navbar-nav me-auto">';
        
        foreach ($this->menuConfig as $menuKey => $menuData) {
            if ($this->hasMenuAccess($menuKey)) {
                $html .= $this->generateMenuItem($menuKey, $menuData, true);
            }
        }
        
        $html .= '</ul>';
        
        // User info and logout
        $html .= '<div class="navbar-nav">';
        $html .= '<div class="nav-item dropdown">';
        $html .= '<a class="nav-link dropdown-toggle text-light" href="#" role="button" data-bs-toggle="dropdown">';
        $html .= '<i class="bi bi-person-circle me-2"></i>' . htmlspecialchars($user['user_id']);
        $html .= '</a>';
        $html .= '<ul class="dropdown-menu dropdown-menu-end">';
        $html .= '<li><h6 class="dropdown-header">Database: ' . htmlspecialchars($user['customer_db']) . '</h6></li>';
        $html .= '<li><hr class="dropdown-divider"></li>';
        $html .= '<li><a class="dropdown-item" href="/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>';
        $html .= '<li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>';
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</nav>';
        
        return $html;
    }

    /**
     * Generate sidebar navigation HTML
     * 
     * @return string Sidebar HTML
     */
    public function generateSidebarMenu(): string
    {
        if (!$this->auth->isLoggedIn()) {
            return '';
        }

        $html = '<div class="sidebar bg-light border-end" style="min-height: calc(100vh - 80px);">';
        $html .= '<div class="sidebar-sticky pt-3">';
        $html .= '<ul class="nav flex-column">';
        
        // Dashboard link
        $html .= '<li class="nav-item">';
        $html .= '<a class="nav-link text-dark" href="/dashboard.php">';
        $html .= '<i class="bi bi-house-door me-2"></i>Dashboard';
        $html .= '</a>';
        $html .= '</li>';
        $html .= '<hr>';
        
        foreach ($this->menuConfig as $menuKey => $menuData) {
            if ($this->hasMenuAccess($menuKey)) {
                $html .= $this->generateSidebarMenuItem($menuKey, $menuData);
            }
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate menu item for top navigation
     * 
     * @param string $menuKey Menu key
     * @param array $menuData Menu data
     * @param bool $isTopLevel Whether this is a top-level menu
     * @return string Menu item HTML
     */
    private function generateMenuItem(string $menuKey, array $menuData, bool $isTopLevel = false): string
    {
        $html = '';
        
        if (isset($menuData['separator'])) {
            return '<li><hr class="dropdown-divider"></li>';
        }
        
        $hasChildren = isset($menuData['items']) && !empty($menuData['items']);
        $icon = isset($menuData['icon']) ? '<i class="' . $menuData['icon'] . ' me-2"></i>' : '';
        
        if ($hasChildren) {
            // Dropdown menu
            $html .= '<li class="nav-item dropdown">';
            $html .= '<a class="nav-link dropdown-toggle text-light" href="#" role="button" data-bs-toggle="dropdown">';
            $html .= $icon . htmlspecialchars($menuData['label']);
            $html .= '</a>';
            $html .= '<ul class="dropdown-menu">';
            
            foreach ($menuData['items'] as $subKey => $subData) {
                if ($this->hasMenuAccess($subKey)) {
                    $html .= $this->generateDropdownItem($subKey, $subData);
                }
            }
            
            $html .= '</ul>';
            $html .= '</li>';
        } else {
            // Simple link
            $url = $menuData['url'] ?? '/under-development.php?menu=' . urlencode($menuKey);
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link text-light" href="' . htmlspecialchars($url) . '">';
            $html .= $icon . htmlspecialchars($menuData['label']);
            $html .= '</a>';
            $html .= '</li>';
        }
        
        return $html;
    }

    /**
     * Generate dropdown menu item
     * 
     * @param string $menuKey Menu key
     * @param array $menuData Menu data
     * @return string Dropdown item HTML
     */
    private function generateDropdownItem(string $menuKey, array $menuData): string
    {
        if (isset($menuData['separator'])) {
            return '<li><hr class="dropdown-divider"></li>';
        }
        
        $hasChildren = isset($menuData['items']) && !empty($menuData['items']);
        
        if ($hasChildren) {
            // Nested dropdown
            $html = '<li class="dropdown-submenu">';
            $html .= '<a class="dropdown-item dropdown-toggle" href="#">';
            $html .= htmlspecialchars($menuData['label']);
            $html .= '</a>';
            $html .= '<ul class="dropdown-menu">';
            
            foreach ($menuData['items'] as $subKey => $subData) {
                if ($this->hasMenuAccess($subKey)) {
                    $html .= $this->generateDropdownItem($subKey, $subData);
                }
            }
            
            $html .= '</ul>';
            $html .= '</li>';
        } else {
            // Simple dropdown link
            $url = $menuData['url'] ?? '/under-development.php?menu=' . urlencode($menuKey);
            $html = '<li>';
            $html .= '<a class="dropdown-item" href="' . htmlspecialchars($url) . '">';
            $html .= htmlspecialchars($menuData['label']);
            $html .= '</a>';
            $html .= '</li>';
        }
        
        return $html;
    }

    /**
     * Generate sidebar menu item
     * 
     * @param string $menuKey Menu key
     * @param array $menuData Menu data
     * @param int $level Nesting level
     * @return string Sidebar item HTML
     */
    private function generateSidebarMenuItem(string $menuKey, array $menuData, int $level = 0): string
    {
        if (isset($menuData['separator'])) {
            return '<li><hr></li>';
        }
        
        $hasChildren = isset($menuData['items']) && !empty($menuData['items']);
        $icon = isset($menuData['icon']) ? '<i class="' . $menuData['icon'] . ' me-2"></i>' : '';
        $indent = str_repeat('&nbsp;&nbsp;', $level * 2);
        
        $html = '';
        
        if ($hasChildren) {
            // Collapsible section
            $collapseId = 'collapse_' . $menuKey . '_' . $level;
            
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link text-dark d-flex justify-content-between align-items-center" ';
            $html .= 'data-bs-toggle="collapse" href="#' . $collapseId . '" role="button">';
            $html .= '<span>' . $indent . $icon . htmlspecialchars($menuData['label']) . '</span>';
            $html .= '<i class="bi bi-chevron-down"></i>';
            $html .= '</a>';
            $html .= '<div class="collapse" id="' . $collapseId . '">';
            $html .= '<ul class="nav flex-column ms-3">';
            
            foreach ($menuData['items'] as $subKey => $subData) {
                if ($this->hasMenuAccess($subKey)) {
                    $html .= $this->generateSidebarMenuItem($subKey, $subData, $level + 1);
                }
            }
            
            $html .= '</ul>';
            $html .= '</div>';
            $html .= '</li>';
        } else {
            // Simple link
            $url = $menuData['url'] ?? '/under-development.php?menu=' . urlencode($menuKey);
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link text-dark" href="' . htmlspecialchars($url) . '">';
            $html .= $indent . $icon . htmlspecialchars($menuData['label']);
            $html .= '</a>';
            $html .= '</li>';
        }
        
        return $html;
    }

    /**
     * Check if user has access to menu
     * 
     * @param string $menuKey Menu key to check
     * @return bool True if user has access
     */
    private function hasMenuAccess(string $menuKey): bool
    {
        // Security menus (starting with 'sec') are never visible to users
        if (str_starts_with($menuKey, 'sec')) {
            return false;
        }
        
        // For now, allow access to all non-security menus
        // This will be enhanced to check against spUser_Menu stored procedure results
        return in_array($menuKey, $this->userMenus) || 
               $this->isPublicMenu($menuKey) ||
               $this->auth->hasMenuAccess($menuKey);
    }

    /**
     * Check if menu is public (always accessible)
     * 
     * @param string $menuKey Menu key
     * @return bool True if public
     */
    private function isPublicMenu(string $menuKey): bool
    {
        $publicMenus = ['file', 'mnuExit'];
        return in_array($menuKey, $publicMenus);
    }

    /**
     * Get breadcrumb navigation
     * 
     * @param string $currentMenu Current menu key
     * @return string Breadcrumb HTML
     */
    public function getBreadcrumb(string $currentMenu): string
    {
        $breadcrumbs = $this->findMenuPath($currentMenu);
        
        if (empty($breadcrumbs)) {
            return '';
        }
        
        $html = '<nav aria-label="breadcrumb">';
        $html .= '<ol class="breadcrumb">';
        $html .= '<li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>';
        
        foreach ($breadcrumbs as $index => $crumb) {
            if ($index === count($breadcrumbs) - 1) {
                $html .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($crumb['label']) . '</li>';
            } else {
                $url = $crumb['url'] ?? '#';
                $html .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($crumb['label']) . '</a></li>';
            }
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }

    /**
     * Find path to menu item for breadcrumbs
     * 
     * @param string $menuKey Menu key to find
     * @param array $menuData Menu data to search (optional)
     * @param array $path Current path (for recursion)
     * @return array Path to menu item
     */
    private function findMenuPath(string $menuKey, array $menuData = null, array $path = []): array
    {
        if ($menuData === null) {
            $menuData = $this->menuConfig;
        }
        
        foreach ($menuData as $key => $data) {
            if ($key === $menuKey) {
                return array_merge($path, [$data]);
            }
            
            if (isset($data['items'])) {
                $result = $this->findMenuPath($menuKey, $data['items'], array_merge($path, [$data]));
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        
        return [];
    }
}