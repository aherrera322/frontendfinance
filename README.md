# Zimple Travel Group - User Authentication System

A complete user authentication system for the Zimple Travel Group website with MySQL database integration.

## Features

- ✅ **User Registration** - Complete registration form with validation
- ✅ **User Login** - Secure login with password hashing
- ✅ **Session Management** - Secure session handling with tokens
- ✅ **Database Integration** - MySQL database with automatic table creation
- ✅ **Security Features** - Password hashing, login attempt limiting, SQL injection protection
- ✅ **Responsive Design** - Mobile-friendly interface matching the main site design
- ✅ **Dashboard** - User dashboard with account information

## Prerequisites

- WAMP/XAMPP server with PHP and MySQL
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Installation

1. **Place files in your WAMP directory**
   ```
   C:\wamp64\www\zimplerentals\
   ```

2. **Start WAMP server**
   - Start Apache and MySQL services

3. **Database Configuration**
   - The system will automatically create the database and tables on first use
   - Default database name: `zimple_travel`
   - Default credentials: `root` (no password)

4. **Update Database Settings (if needed)**
   - Edit `auth/config.php` if you need to change database settings:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'zimple_travel');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

## File Structure

```
zimplerentals/
├── index.html              # Main homepage
├── login.html              # Login page
├── register.html           # Registration page
├── dashboard.html          # User dashboard
├── partners.html           # Partners page
├── privacy-policy.html     # Privacy policy
├── auth/
│   ├── config.php          # Database configuration
│   ├── register.php        # Registration handler
│   ├── login.php           # Login handler
│   ├── logout.php          # Logout handler
│   └── check_session.php   # Session validation
└── public/
    └── images/             # Logo and images
```

## Usage

### For Users

1. **Register**: Visit `http://localhost/zimplerentals/register.html`
2. **Login**: Visit `http://localhost/zimplerentals/login.html`
3. **Dashboard**: Access dashboard after successful login

### For Developers

#### Database Tables

The system automatically creates these tables:

- **users** - User account information
- **user_sessions** - Active user sessions
- **login_attempts** - Login attempt tracking

#### API Endpoints

- `POST auth/register.php` - User registration
- `POST auth/login.php` - User login
- `POST auth/logout.php` - User logout
- `GET auth/check_session.php` - Session validation

## Security Features

- **Password Hashing**: Uses PHP's `password_hash()` with bcrypt
- **SQL Injection Protection**: Prepared statements throughout
- **Session Security**: Secure session tokens with expiration
- **Login Attempt Limiting**: Prevents brute force attacks
- **Input Validation**: Comprehensive input sanitization
- **CSRF Protection**: Session-based token validation

## Customization

### Styling
- All pages use Tailwind CSS with the primary color `#117372`
- Consistent design across all authentication pages
- Responsive design for mobile devices

### Email Verification
- Email verification is included but disabled by default
- To enable, uncomment the verification check in `auth/login.php`
- Implement email sending in `auth/register.php`

### Password Requirements
- Minimum 8 characters
- Can be customized in `auth/register.php`

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Ensure MySQL is running
   - Check database credentials in `auth/config.php`
   - Verify database permissions

2. **Session Not Working**
   - Check if cookies are enabled
   - Verify PHP session configuration
   - Check file permissions

3. **Registration Fails**
   - Check PHP error logs
   - Verify database table creation
   - Ensure all required fields are provided

### Error Logs
- Check WAMP error logs: `C:\wamp64\logs\`
- PHP errors are logged to the system error log

## Future Enhancements

- [ ] Email verification system
- [ ] Password reset functionality
- [ ] Two-factor authentication
- [ ] Social login (Google, Facebook)
- [ ] User profile management
- [ ] Admin panel
- [ ] Email notifications

## Support

For technical support or questions, please check the error logs or contact the development team.

---

**Note**: This is a development version. For production use, ensure proper security measures are implemented including HTTPS, proper server configuration, and regular security updates.



