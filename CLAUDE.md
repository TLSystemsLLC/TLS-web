# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This repository contains both a legacy VB6 Transportation Management System (TLS Operations) and a modern PHP web application conversion. The project maintains the same SQL Server database backend while migrating the user interface to a modern web-based system.

## Architecture

### Multi-Application Structure
- **`operations/`**: Legacy VB6 application source code (167 forms, 277 total files)
- **`tls-basedb/`**: Complete SQL Server database schema with tables, stored procedures, functions, and security policies
- **`tls-web/`**: Modern PHP web application providing equivalent functionality

### Database-Centric Design
The system is built around SQL Server stored procedures rather than ORM patterns:
- All business logic resides in stored procedures (not application code)
- Each customer operates from a separate database instance
- Authentication uses `spUser_Login`, menu security uses `spUser_Menus` and `spUser_Menu`
- Database connections follow connect-execute-disconnect pattern (no connection pooling)

### Web Application Architecture (tls-web/)
**Core Classes:**
- `Auth`: Handles three-field authentication (Customer/UserID/Password) and session management
- `Database`: SQL Server connection management with security-focused error handling  
- `MenuManager`: Generates responsive menus based on user permissions from stored procedures
- `Session`: Secure session management with timeout and regeneration
- `Config`: Environment-based configuration management

**Security Model:**
- Menu items prefixed with 'sec' are hidden security permissions, never displayed to users
- Real-time permission checking against `spUser_Menu` stored procedure
- Session-based authentication with configurable timeout
- All database queries use parameterized statements

## Production Deployment Status

### ✅ CURRENT DEPLOYMENT - FULLY OPERATIONAL
**Environment:** MAMP (Apache 2.4.62 + PHP 8.3.14) on macOS  
**URL:** http://localhost:8888/tls/  
**Database:** SQL Server 2017 at 35.226.40.170  
**Status:** Production-ready with live database authentication

### Deployment Commands (COMPLETED)
```bash
# Application deployed to MAMP
cp -r tls-web/ /Applications/MAMP/htdocs/tls/
chmod -R 755 /Applications/MAMP/htdocs/tls/
chmod 644 /Applications/MAMP/htdocs/tls/config/.env

# Verified working configuration
# ✅ Apache mod_rewrite enabled and functional
# ✅ URL routing working correctly (/tls/ prefix)
# ✅ Database connectivity confirmed
# ✅ Authentication with real user credentials operational
```

### Verified Working Databases
- **DEMO**: Contains `spUser_Login`, `spUser_Menus`, active users (chodge, cknox, cscott, dhatfield, egarcia)
- **TLSYS**: Contains all required stored procedures, active users (SYSTEM, tlyle, wjohnston)

### Database Connection (PRODUCTION CONFIG)
```php
// Working PDO configuration - DO NOT CHANGE
$dsn = "sqlsrv:Server=35.226.40.170,1433;Database={$database}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
    // NOTE: Do not add PDO::ATTR_TIMEOUT - causes connection failures
];

// Test connectivity (VERIFIED WORKING)
$db = new Database('35.226.40.170', 'Admin', 'Aspen4Home!');
$db->connect('DEMO'); // or 'TLSYS'
$result = $db->executeStoredProcedureWithReturn('spUser_Login', ['userid', 'password']);
```

### Adding New Web Forms (PRODUCTION PATTERN)
When converting VB6 forms to PHP pages:

```php
// 1. Create new PHP file in /Applications/MAMP/htdocs/tls/[subdirectory]/
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MenuManager.php';

// 2. Initialize authentication (PRODUCTION CONFIG)
$auth = new Auth(
    Config::get('DB_SERVER'),  // 35.226.40.170
    Config::get('DB_USERNAME'), // Admin
    Config::get('DB_PASSWORD')  // Aspen4Home!
);

// 3. Require authentication (CORRECT URL PATH)
$auth->requireAuth('/tls/login.php');

// 4. Check specific menu permissions
if (!$auth->hasMenuAccess('mnuSpecificMenu')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

// 5. Use existing stored procedures for all data operations
$db = new Database(Config::get('DB_SERVER'), Config::get('DB_USERNAME'), Config::get('DB_PASSWORD'));
$db->connect($auth->getCurrentUser()['customer_db']);
$results = $db->executeStoredProcedure('spExistingProcedure', $parameters);

// 6. Generate page with MenuManager for consistent navigation
$menuManager = new MenuManager($auth);
echo $menuManager->generateMainMenu();
?>
```

### Critical URL Routing (REQUIRED)
**ALL internal links must use `/tls/` prefix:**
- ✅ Login redirects: `/tls/login.php`
- ✅ Dashboard links: `/tls/dashboard.php`  
- ✅ Logout links: `/tls/logout.php`
- ✅ Under development: `/tls/under-development.php?menu=menuname`

## Configuration Management

### Environment Variables (.env)
Critical settings stored in `tls-web/config/.env`:
- `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`: SQL Server connection details
- `SESSION_TIMEOUT`: Authentication session duration
- `APP_DEBUG`: Controls error display vs. logging
- All configuration accessed via `Config::get('KEY_NAME')`

### Menu Structure
Menu definitions in `tls-web/config/menus.php` map VB6 menu structure to web navigation:
- Hierarchical array structure matches VB6 frmMDI menu layout with logical improvements
- Each menu item includes security key, label, icon, and URL
- Security permissions automatically filter visible menus per user
- Payroll menu consolidates all settlement processing (Unit, Agent, Carrier) with check lookup functions
- Development functions organized under Systems menu for administrative grouping
- Mobile functionality integrated under Dispatch menu for operational workflow
- Enhanced visual styling through CSS without JavaScript complexity

## Database Integration Patterns

### Authentication Flow
1. User provides Customer (database name), UserID, Password
2. System connects to specified customer database
3. Calls `spUser_Login` stored procedure with credentials
4. On success (return code 0), loads user menus via `spUser_Menus`
5. Stores session with user permissions and database context

### Data Operations
- **Always use stored procedures** - no direct table access
- **Connect per operation** - establish connection, execute, disconnect immediately
- **Error handling** - log detailed errors server-side, show generic messages to users
- **Parameter binding** - all queries use PDO parameterized statements

### Multi-Database Handling
Each customer operates in a separate database:
- Authentication establishes which database to use
- All subsequent operations occur within that customer's database context
- Session maintains current database name for proper routing

## Legacy VB6 Considerations

When working with the VB6 codebase in `operations/`:
- Forms (.frm) contain UI definitions and event handlers
- Classes (.cls) contain business objects and data access logic  
- Modules (.bas) contain shared functions and declarations
- Business logic should be migrated to stored procedures, not web application code
- UI patterns should be converted to responsive Bootstrap equivalents

## Security Architecture

### Authentication Security
- Three-factor authentication (database + user + password)
- Session management with secure cookies (HttpOnly, Secure, SameSite)
- Automatic session timeout and regeneration
- Input validation on all authentication fields

### Database Security  
- No hardcoded credentials (all via .env configuration)
- Parameterized queries prevent SQL injection
- Connection-per-request model limits exposure
- Detailed error logging separate from user-facing messages

### Menu Security
- Permission-based menu visibility using database-stored user permissions
- Real-time authorization checks on each menu access
- Hidden security permissions (sec* menus) control backend functionality
- Session-based permission caching with database validation

## Current Project Status (January 2025)

### ✅ PHASE 1 COMPLETE: Foundation Infrastructure
**Status:** Production-ready web application framework deployed and operational

### ✅ COMPREHENSIVE DOCUMENTATION COMPLETE
**Status:** Complete inventory of all completed pages documented in `COMPLETED-PAGES.md`
- **8 Functional Pages** documented with VB6 origins, security permissions, and stored procedures
- **15+ Database Tables** usage documented including new web-specific enhancements
- **Performance Metrics** tracked across all operational pages
- **Browser Compatibility** verified (Chrome, Safari, Firefox, Mobile)

**Completed Components:**
- ✅ **Authentication System**: Three-field login with real database validation
- ✅ **Authorization System**: Menu-based permissions via stored procedures (193 permissions loading correctly)
- ✅ **Database Integration**: SQL Server 2017 connectivity with CWKI2 production database
- ✅ **User Interface**: Responsive Bootstrap 5 design with mobile support and sticky navigation
- ✅ **Session Management**: Secure session handling with timeout and proper data loading
- ✅ **URL Routing**: Clean URLs with mod_rewrite (.htaccess configured)  
- ✅ **Error Handling**: User-friendly messages with detailed admin logging
- ✅ **Menu System**: Dynamic navigation based on database permissions (167 VB6 forms mapped)
- ✅ **Company Integration**: Company name display via spCompany_Get stored procedure
- ✅ **Menu Restructuring**: Logical grouping (Mobile under Dispatch, Development under Systems)
- ✅ **Navigation Enhancements**: Sticky top navigation with scrollable dropdowns for long menus
- ✅ **User Security Management**: Complete web-based permission management system

**Ready for Production Use:**
- Users can log in with existing credentials (CWKI2/tlyle/Tls0901 confirmed working)
- Full navigation system operational with all 193 menu permissions
- All security features active with proper session management
- Database operations working with real production data
- Professional user experience with company name display

### ✅ RECENT ENHANCEMENTS (August 2025)

**Menu Structure Optimization:**
- Corrected menu hierarchy to match original VB6 frmMDI structure
- Moved Development menu under Systems (administrative grouping)
- Moved Mobile functionality under Dispatch (operational grouping)
- Consolidated all settlement processing under Payroll menu with logical grouping:
  - Unit Settlements (formerly Process Owner Settlements)
  - Agent Settlements (formerly Process Agent Settlements) 
  - Carrier Settlements (formerly Process Carrier Settlements)
- Relocated check lookup functions to appropriate payroll settlement sections
- Removed unnecessary separators throughout menu structure for cleaner navigation
- Resolved session caching issues causing incorrect permission counts

**User Experience Improvements:**
- Implemented sticky navigation bar that stays at top during scroll
- Added scrollable dropdowns for long menus (max 70% viewport height)
- Custom scrollbar styling with webkit enhancements for better visual experience
- Company name display on dashboard via spCompany_Get stored procedure integration
- Customer ID preserved in user dropdown menu for reference (labeled "Customer ID")
- Profile link properly routes to under-development page instead of PHP info
- Enhanced dropdown section headers with gradient backgrounds and blue accent borders
- Added hover effects and visual hierarchy for better menu navigation

**Technical Enhancements:**
- Created `/tls/css/app.css` for global application styles with comprehensive menu styling
- Enhanced Auth class with company information retrieval via spCompany_Get stored procedure
- Improved session data structure with company_info and company_name fields
- Fixed menu permission loading to ensure all 193 permissions are retrieved correctly
- Added debugging capabilities for menu permission troubleshooting
- Implemented flattened menu approach avoiding JavaScript complexity per user preference
- Updated MenuManager class with enhanced dropdown styling and CSS link integration

**Payroll Menu Consolidation:**
- All settlement processing functions now logically grouped under Payroll menu
- Check lookup functions positioned as first items in each settlement section
- Clear section headers distinguish between Unit, Agent, and Carrier settlements
- Maintains all existing security permissions and stored procedure integrations

**Production Readiness:**
- All 193 menu permissions loading correctly from CWKI2 database
- Session management working reliably with proper data persistence
- Menu structure now matches user expectations from VB6 system with logical improvements
- Navigation optimized for both desktop and mobile use with enhanced visual hierarchy
- Company integration provides professional user experience with proper branding

### ✅ PHASE 1.5 COMPLETE: Administrative Tools
**Status:** User Security management system implemented and operational

**User Security System (`/systems/user-security.php`):**
- ✅ **Database-Driven Permissions**: Dynamic loading from `tSecurity` and `tSecurityGroups` tables
- ✅ **Modern Interface**: Grouped card layout with Bootstrap 5 responsive design
- ✅ **Role Templates**: Dispatch, Broker, and Accounting predefined permission sets
- ✅ **Optimized Performance**: Save only changed permissions (not entire permission set)
- ✅ **Security Categories**: Includes all 'sec*' permissions in dedicated Security section
- ✅ **Real-time Tracking**: Live permission counts and change detection
- ✅ **Bulk Operations**: Grant/deny all visible permissions, search filtering
- ✅ **User-Friendly**: Clean interface without duplicate descriptions

**Technical Implementation:**
- Database integration with `tSecurity` (Menu, Description) and `tSecurityGroups` (UserGroup, Menu)
- AJAX-powered interface with real-time change tracking
- Optimized database operations (only saves actual changes)
- Comprehensive error handling and logging
- Mobile-responsive design matching application standards

### ✅ PHASE 2 IN PROGRESS: Load Entry Form Conversion
**Status:** Load Entry form implementation with Progressive Hybrid Approach

**Load Entry Form (`/tls/dispatch/load-entry.php`) - OPERATIONAL:**
- ✅ **Progressive Hybrid UI**: Smart defaults with expandable advanced sections
- ✅ **Complete Stops Management**: Drag-and-drop reordering with role-based badges (origin=green, intermediate=yellow, destination=red)
- ✅ **Tender Information**: Full tender search with manual entry and autocomplete integration
- ✅ **Customer Autocomplete**: Search with auto-populated billing information
- ✅ **Origin/Destination Lookup**: NameQual='QK' filtering with QuickKey search capability
- ✅ **Customer Locations**: Auto-popup on click, 2-digit formatting, city trimming
- ✅ **Commodity Autocomplete**: Historical data from tLoadHeader
- ✅ **Independent Column Layout**: Flexbox layout solving height dependency issues
- ✅ **Authentication Integration**: Full security with mnuLoadEntry permission checking
- ✅ **Database Integration**: spLoadHeader_Get and spLoadHeader_Save stored procedures
- ✅ **Responsive Design**: Bootstrap 5 with mobile-friendly interface

**Technical Implementation:**
- **TLSAutocomplete Class**: Reusable JavaScript component for all autocomplete functionality
- **API Endpoint**: `/api/lookup.php` with specialized functions for each lookup type including stops and tenders
- **Smart Filtering**: Active customers (EndDate = '1899-12-30 00:00:00.000'), QK addresses only
- **Independent Layout System**: Flexbox columns (48% + 2% margin left, 50% right) for height independence
- **Drag-and-Drop Integration**: HTML5 drag API with sortable stops and role assignment
- **Data Validation**: Real-time validation with user-friendly error messages
- **Performance Optimization**: Debounced search, configurable result limits
- **Progressive Enhancement**: Core functionality works without JavaScript

**Business Logic Implementation:**
- **Customer Search**: Filters active customers, displays company name with customer key
- **Address Search**: Uses QuickKey (user-known values) with NameQual='QK' filtering
- **Location Management**: Customer-specific locations with formatted display
- **Commodity History**: Distinct commodity list from historical load data

**Debugging and Testing:**
- **Debug Page**: `/debug-autocomplete.php` for API testing and database verification
- **Error Handling**: Comprehensive logging with user-friendly messages
- **Database Validation**: Confirms QK address data availability and proper filtering

### ✅ PHASE 2.5 COMPLETE: Driver Maintenance Form Conversion
**Status:** Driver Maintenance form fully operational with enhanced security features

**Driver Maintenance Form (`/tls/safety/driver-maintenance.php`) - OPERATIONAL:**
- ✅ **Progressive Hybrid UI**: Clean interface with smart defaults and expandable sections
- ✅ **Advanced Search**: Driver lookup with active/inactive toggle and real-time autocomplete
- ✅ **PII Protection**: DriverID (Tax ID) hidden behind security prompt, DriverKey visible for reference
- ✅ **Complete CRUD Operations**: Create, read, update driver records via stored procedures
- ✅ **Form Validation**: Client and server-side validation with user-friendly error messages
- ✅ **Change Tracking**: Real-time change detection with unsaved changes protection
- ✅ **Authentication Integration**: Full security with mnuDriverMaint permission checking
- ✅ **Database Integration**: spDriver_Get and spDriver_Save stored procedures
- ✅ **Standardized Theme**: Consistent styling with load entry screen design

**Technical Implementation:**
- **API Integration**: Enhanced `/api/lookup.php` with driver search functionality
- **Security Model**: DriverKey for database operations, DriverID (PII) protected behind user action
- **Error Resolution**: Fixed method calls (executeStoredProcedure vs executeStoredProcedureWithReturn)
- **Type Safety**: Proper string casting for htmlspecialchars() compatibility
- **Form Tracker Integration**: TLSFormTracker component for change detection and save management

### ✅ PHASE 2.6 COMPLETE: UI Standardization Initiative  
**Status:** All deployed screens standardized with consistent theme and functionality (September 2025)

**Standardized UI Components Created:**
- ✅ **Global CSS Theme** (`/tls/css/app.css`): Comprehensive standardized styling based on load entry design
- ✅ **Form Tracker Component** (`/tls/js/tls-form-tracker.js`): Reusable change detection and save functionality
- ✅ **Page Header Standard**: `.tls-page-header` with title and top action buttons layout
- ✅ **Form Card System**: `.tls-form-card` with consistent hover effects and professional styling
- ✅ **Button Standards**: `.tls-btn-primary`, `.tls-btn-secondary`, `.tls-btn-warning` with gradients
- ✅ **Change Tracking UI**: `.tls-save-indicator` and `.tls-change-counter` for consistent save states
- ✅ **PII Protection Theme**: `.tls-pii-section` for sensitive data with warning styling
- ✅ **Responsive Design**: Mobile-friendly breakpoints and touch-optimized interactions

**Screens Updated to Standard Theme:**
- ✅ **Driver Maintenance**: Top buttons, standardized cards, form tracker integration, PII protection
- ✅ **User Security Management**: Standardized header, card styling, change counter integration
- ✅ **Load Entry**: Reference design (already matched standard)
- ✅ **Other Screens**: Dashboard, under-development reviewed and confirmed appropriate

**Key Achievements:**
- **Visual Consistency**: All form screens share identical professional aesthetic
- **User Experience**: Save/Cancel buttons always at top, real-time change indicators, navigation protection
- **Developer Efficiency**: Reusable components reduce future development time
- **Maintainability**: Centralized styling and components prevent design drift

**Integration Benefits:**
- **Change Detection**: Automatic tracking of form modifications across all screens
- **Save Management**: Consistent save/cancel/reset behavior with confirmation dialogs
- **Navigation Protection**: Browser warns users about unsaved changes
- **Mobile Optimization**: Responsive design works consistently across all devices

### ✅ PHASE 2.7 COMPLETE: UI Refinements and Color-Coded Button System  
**Status:** Enhanced button styling, improved save indicators, and refined search layouts (January 2025)

**Button System Enhancements:**
- ✅ **State-Based Color Coding**: Green primary buttons for save actions, red secondary for cancel/reset
- ✅ **Disabled State Consistency**: Proper gray styling with maintained button dimensions when disabled
- ✅ **Button Size Uniformity**: Fixed disabled button sizing to match enabled buttons exactly (48px min-height, consistent padding)
- ✅ **Visual State Feedback**: Bright colors when active, muted when inactive, consistent hover effects
- ✅ **Right-Aligned Positioning**: All action buttons properly positioned on right side of headers
- ✅ **Size Consistency**: Fixed disabled button dimensions to match enabled buttons perfectly

**Save Indicator Improvements:**
- ✅ **Banner-Style Indicators**: Replaced floating popups with inline warning banners
- ✅ **Non-Blocking Design**: Save indicators no longer cover buttons or interfere with workflow
- ✅ **Consistent Styling**: Yellow/amber gradient banners with professional appearance
- ✅ **Proper Positioning**: Positioned below headers in document flow, not as overlays

**Driver Search Layout Optimization:**
- ✅ **Single Digit DriverKey Support**: Accepts single digit driver keys (1-9) for search and direct entry
- ✅ **Direct DriverKey Entry**: Load Driver button works with typed DriverKey values without requiring autocomplete selection
- ✅ **Dual Input Support**: Single search field handles both driver names and DriverKey numbers  
- ✅ **Exact DriverKey Matching**: Numeric searches return exact matches only (no partial matching)
- ✅ **PII Protection Enhancement**: Removed DriverID from all search queries for security
- ✅ **Auto-Submit Restoration**: Driver selection from dropdown automatically loads driver form
- ✅ **Optimized Layout**: Load Driver button adjacent to search, Include Inactive below with proper alignment

**Technical Improvements:**
- **API Security**: Removed DriverID (Tax ID) from lookup queries preventing PII exposure
- **Search Logic**: Implemented numeric detection for exact DriverKey vs fuzzy name matching
- **CSS Enhancements**: Added `!important` declarations to ensure button styling consistency
- **Layout Engineering**: Three-row structure with precise Bootstrap grid positioning
- **Auto-Load Functionality**: Restored seamless driver selection workflow

**User Experience Benefits:**
- **Clear Visual Hierarchy**: Color-coded buttons provide instant action recognition
- **Professional Appearance**: Consistent button styling across all application screens
- **Improved Workflow**: Non-blocking save indicators maintain operational efficiency
- **Enhanced Search**: Faster DriverKey lookup with better result accuracy
- **Better Layout**: Logical component positioning with improved space utilization

**Next Steps for Phase 2:**
1. **Enhanced Load Entry Features**: Add load details, dispatch information, routing
2. **Voucher Entry** (`frmAPVoucher.frm` → `/tls/accounting/voucher-entry.php`)
3. **Additional Form Conversions**: Apply standardized theme to all new forms

## Development Workflow (CURRENT)

1. **Priority Selection**: Choose VB6 form based on business priority
2. **VB6 Form Analysis**: Examine existing .frm file for business logic and UI patterns
3. **Database Procedure Review**: Identify existing stored procedures used by VB6 form
4. **PHP Page Creation**: Build responsive web equivalent using production authentication pattern
5. **Menu Integration**: Update `/tls/config/menus.php` to point to new PHP page
6. **Permission Integration**: Ensure proper menu security keys and authorization checks
7. **Testing**: Verify functionality against VB6 equivalent with same database operations
8. **Deployment**: File goes directly to `/Applications/MAMP/htdocs/tls/` directory structure

## Lessons Learned

### Menu Structure and User Experience
- SQL Server PDO driver has specific attribute limitations - use minimal options
- MAMP environment requires careful path management for URL routing
- Customer terminology preferences are critical for user acceptance
- Stored procedure integration maintains business logic separation
- Bootstrap provides excellent responsive foundation for legacy application modernization
- Session caching can cause permission discrepancies - always clear session when testing different databases
- Menu structure alignment with original system is crucial for user adoption
- Visual hierarchy through CSS styling is preferred over JavaScript complexity
- Flattened menu structures work better than nested dropdowns for complex navigation
- Consolidating related functions under logical parent menus improves workflow efficiency
- Company branding integration (names vs. database IDs) enhances professional appearance

### Technical Architecture Patterns
- Connect-execute-disconnect pattern essential for multi-database environments
- Parameterized stored procedure calls prevent SQL injection while maintaining business logic
- Session-based permission caching with real-time validation balances performance and security
- CSS-only enhancements preferred over JavaScript for menu functionality
- Sticky navigation with scrollable dropdowns solves long menu usability issues
- Global CSS files enable consistent styling across application components
- Database-driven configurations provide better maintainability than hardcoded values
- AJAX interfaces with real-time change tracking enable superior user experiences
- Performance optimization: save only changed data, not entire datasets
- Null-safe DOM manipulation prevents JavaScript errors in complex interfaces

### Form Conversion Patterns (Load Entry Experience)
- **Progressive Hybrid Approach**: Start with smart defaults, expand to advanced features on demand
- **Autocomplete Architecture**: Centralized API with specialized lookup functions per data type
- **Business Date Logic**: `1899-12-30 00:00:00.000` = active/not entered (critical for filtering)
- **Address Filtering**: `NameQual='QK'` addresses with QuickKey search for user recognition
- **Layout Independence**: Flexbox columns prevent height dependencies between card sections
- **Stops Management**: HTML5 drag-and-drop with role-based visual indicators and seamless reordering
- **Manual Entry Integration**: Quick Key support in all manual address forms with undo capabilities
- **Error Resolution Process**: Authentication methods, JavaScript syntax, SQL column names, parameter binding
- **Debugging Tools**: Dedicated debug pages for API testing and database verification
- **TLS-Specific Data Patterns**: Customer locations, commodity history, active status filtering, tender information
- **Reusable Components**: TLSAutocomplete JavaScript class for consistent search behavior
- **Bootstrap Layout Optimization**: Replace row dependencies with independent flexbox columns for complex forms

### UI Standardization Patterns (Driver Maintenance & Form Tracker Experience)
- **Standardized Theme Architecture**: Centralized CSS in app.css for consistent styling across all screens
- **Form Change Tracking**: TLSFormTracker JavaScript component for universal change detection
- **Top Button Pattern**: Save/Cancel buttons in page header for better visibility and consistency
- **PII Protection UI**: Hidden sensitive fields with explicit user action required for viewing
- **DriverKey vs DriverID**: Database keys visible, PII (Tax ID) protected behind security prompts
- **Method Selection**: Use executeStoredProcedure for data, executeStoredProcedureWithReturn for status codes
- **Type Safety**: Always cast to string for htmlspecialchars() to prevent PHP fatal errors
- **Change Counter Display**: Real-time visual feedback showing number of unsaved changes
- **Navigation Protection**: Browser beforeunload event prevents accidental data loss
- **Card Styling Consistency**: tls-form-card class provides uniform appearance across all forms
- **Button State Management**: Disabled until changes detected, visual state changes on hover
- **Save Indicator Positioning**: Fixed position for persistent visibility during scrolling
- **Responsive Breakpoints**: Mobile-first design with graceful degradation
- **Component Reusability**: Single tracker instance handles all form change detection needs
- You do not need to init the git repository, you must use the tls-web folder