# TLS Operations Web Application

A modern PHP web application conversion of the VB6 TLS Operations (Transportation Management System), maintaining the same SQL Server database backend with enhanced security and responsive design.

## Features

- **Secure Authentication**: Three-field login system (Customer/UserID/Password) 
- **Menu-Based Security**: Integration with existing `spUser_Menus` and `spUser_Menu` stored procedures
- **Modern UI**: Bootstrap 5 responsive design with dark/light theme support
- **Database Integration**: Direct SQL Server connectivity using existing stored procedures
- **Session Management**: Secure session handling with timeout and regeneration
- **Modular Architecture**: Object-oriented PHP with proper separation of concerns

## Requirements

- **PHP 8.3+** with extensions:
  - PDO
  - PDO_SQLSRV (SQL Server driver)
  - OpenSSL
  - Session support
- **SQL Server Database** (existing TLS database structure)
- **Apache Web Server** with mod_rewrite enabled
- **Bootstrap 5.3** (loaded via CDN)

## Installation

1. **Clone/Copy the application** to your web server directory:
   ```bash
   cp -r tls-web/ /path/to/your/webserver/
   ```

2. **Configure Environment**:
   ```bash
   cd tls-web/config/
   cp .env.example .env
   # Edit .env with your specific settings
   ```

3. **Set Permissions**:
   ```bash
   chmod 644 config/.env
   chmod -R 755 tls-web/
   ```

4. **Configure Apache**:
   - Ensure `.htaccess` files are processed
   - Enable `mod_rewrite`
   - Configure document root to point to `tls-web/`

## Configuration

### Database Connection

Edit `config/.env`:

```env
DB_SERVER=your_sql_server_ip
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password
DB_PORT=1433
```

### Application Settings

```env
APP_NAME="TLS Operations"
APP_ENVIRONMENT=production
APP_DEBUG=false
SESSION_TIMEOUT=3600
```

## File Structure

```
tls-web/
├── classes/
│   ├── Auth.php              # Authentication management
│   ├── Database.php          # SQL Server connection handling
│   ├── MenuManager.php       # Menu generation and security
│   └── Session.php           # Session management
├── config/
│   ├── .env                  # Environment configuration
│   ├── .env.example          # Environment template
│   ├── config.php            # Configuration loader
│   └── menus.php             # Menu structure definition
├── dashboard.php             # Main dashboard page
├── login.php                 # User login page
├── logout.php                # Logout handler
├── index.php                 # Application entry point
├── under-development.php     # Placeholder for incomplete features
├── .htaccess                 # Apache rewrite rules
├── .gitignore                # Git ignore rules
└── README.md                 # This file
```

## Security Features

### Authentication
- **Three-field login**: Customer database, User ID, and Password
- **Stored procedure validation**: Uses existing `spUser_Login` procedure
- **Session security**: HttpOnly, Secure, SameSite cookies
- **Session timeout**: Configurable automatic logout

### Menu Security
- **Permission-based menus**: Integration with `spUser_Menus` stored procedure
- **Security menu hiding**: Menus prefixed with 'sec' are never visible to users
- **Real-time permission checking**: Uses `spUser_Menu` for individual menu access
- **SQL injection protection**: Parameterized queries throughout

### Database Security
- **Connection per request**: No persistent connections
- **Error handling**: Generic error messages to users, detailed logging for administrators
- **Input validation**: All user inputs validated and sanitized

## Usage

### Login Process
1. Navigate to the application URL
2. Enter Customer (database name), User ID, and Password
3. System validates credentials using `spUser_Login` stored procedure
4. On success, user menus are loaded using `spUser_Menus`
5. User is redirected to dashboard

### Menu Navigation
- **Top Navigation**: Main menu categories with dropdowns
- **Sidebar Navigation**: Collapsible menu tree for detailed navigation
- **Breadcrumbs**: Context-aware navigation path
- **Quick Access**: Dashboard shortcuts to frequently used functions

### Security Permissions
- Menu visibility is controlled by user permissions stored in the database
- Security menus (prefixed with 'sec') provide special permissions but are never visible
- Each menu click can be validated against `spUser_Menu` stored procedure

## Development Status

### ✅ Completed
- User authentication system
- Menu security integration  
- Database connection management
- Responsive navigation system
- Session management
- Basic dashboard interface

### 🔄 In Progress
- Individual form conversions from VB6
- Business logic implementation
- Data entry screens
- Reporting system

### 📋 Planned
- Load entry and management
- Dispatch operations
- Accounting modules (A/P, A/R, G/L, P/R)
- Safety and driver management
- Imaging system integration
- Mobile messaging
- Report generation

## Converting VB6 Forms

When converting individual VB6 forms to PHP pages:

1. **Create new PHP file** in appropriate subdirectory (e.g., `/dispatch/load-entry.php`)
2. **Include authentication check**:
   ```php
   require_once __DIR__ . '/../classes/Auth.php';
   $auth->requireAuth('/login.php');
   ```
3. **Check menu permissions**:
   ```php
   if (!$auth->hasMenuAccess('mnuLoadEntry')) {
       header('HTTP/1.0 403 Forbidden');
       exit;
   }
   ```
4. **Use existing stored procedures** for data operations
5. **Maintain same validation rules** as VB6 version
6. **Follow Bootstrap responsive design patterns**

## Database Considerations

- **Same Database Structure**: Uses existing SQL Server database unchanged
- **Stored Procedures**: All database interactions use existing stored procedures
- **Connection Management**: Connect, execute, disconnect pattern for security
- **Error Logging**: Database errors logged with context for troubleshooting
- **Multi-Database Support**: Each customer has separate database as in VB6

## Troubleshooting

### Common Issues

**Login Problems**:
- Verify database server connectivity
- Check credentials in `.env` file
- Ensure `spUser_Login` stored procedure exists and is accessible
- Check PHP error logs for database connection errors

**Menu Not Displaying**:
- Verify user has menu permissions in `tUserMenus` table
- Check that `spUser_Menus` procedure returns expected results
- Ensure menu keys match between database and `config/menus.php`

**Session Issues**:
- Check PHP session configuration
- Verify cookie settings (Secure flag requires HTTPS)
- Check session timeout settings
- Clear browser cookies and try again

### Logging

Application logs errors to:
- PHP error log (as configured in PHP)
- Custom application log (if LOG_FILE configured in .env)

Check these logs for detailed error information.

## Future Enhancements

- **API Development**: RESTful API for mobile applications
- **Real-time Updates**: WebSocket integration for live load tracking
- **Advanced Reporting**: Enhanced report builder with export capabilities
- **Mobile Optimization**: Progressive Web App (PWA) features
- **Integration APIs**: Third-party logistics and accounting system integration

## Support

For technical support or feature requests:
1. Check existing documentation
2. Review PHP and SQL Server error logs
3. Verify database connectivity and stored procedure functionality
4. Contact system administrator for database-related issues

---

**Note**: This web application maintains full compatibility with the existing VB6 system and database structure. Both systems can operate simultaneously during the transition period.# TLS-web
