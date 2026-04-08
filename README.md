# PrimeCart Backend API - Laravel Documentation

## 📋 Overview

PrimeCart Backend is a robust RESTful API built with Laravel 13, providing comprehensive e-commerce functionality with JWT authentication, role-based access control, and payment gateway integrations. This API serves as the backbone for the PrimeCart e-commerce platform.

## 🛠️ Tech Stack

- **Framework**: Laravel 13
- **PHP Version**: ^8.3
- **Authentication**: JWT (php-open-source-saver/jwt-auth)
- **Database**: SQLite (default), MySQL/PostgreSQL supported
- **Queue System**: Laravel Queues
- **File Processing**: Laravel Excel, DomPDF

## 🚀 Features

### Authentication & Authorization
- JWT-based authentication system
- Role-based access control (Admin/Customer)
- Password reset with OTP verification
- Admin-specific authentication endpoints

### Core E-commerce Functionality
- Product management with inventory tracking
- Shopping cart system
- Order processing and management
- Payment gateway integrations (M-PESA, DPO, Flutterwave)
- User activity logging

### Admin Features
- User management (activate/deactivate)
- Activity monitoring and logs
- Report generation (Excel/PDF exports)
- Analytics dashboard data

### Security & Performance
- Request validation and sanitization
- Rate limiting
- CORS configuration
- Queue-based job processing
- Database optimization

## 📦 Installation & Setup

### Prerequisites
- PHP ^8.3
- Composer
- Node.js & NPM
- Database (SQLite/MySQL/PostgreSQL)

### Step 1: Clone & Install Dependencies
```bash
git clone <repository-url>
cd PRIMECART
composer install
npm install
```

### Step 2: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env` file:
```env
APP_NAME=PrimeCart
APP_ENV=local
APP_KEY=base64:your-generated-key
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=primecart
# DB_USERNAME=root
# DB_PASSWORD=

JWT_SECRET=your-jwt-secret-key
```

### Step 3: Database Setup
```bash
php artisan migrate
php artisan db:seed
```

### Step 4: Link Storage
```bash
php artisan storage:link
```

### Step 5: Start Development Server
```bash
# Using Laravel Sail (Docker)
composer run dev

# Or manually
php artisan serve
php artisan queue:work
```

## 🔐 Authentication

### JWT Configuration
The API uses JWT tokens for authentication. Configure in `config/jwt.php`:

```php
'secret' => env('JWT_SECRET'),
'ttl' => env('JWT_TTL', 60), // Token expiration in minutes
'refresh_ttl' => env('JWT_REFRESH_TTL', 20160),
'algo' => 'HS256',
```

### Authentication Endpoints

#### Public Routes
```http
POST /api/auth/register          # User registration
POST /api/auth/login             # User login
POST /api/auth/admin/login       # Admin login
POST /api/auth/forgot-password   # Request password reset
POST /api/auth/verify-otp        # Verify OTP for password reset
POST /api/auth/reset-password    # Reset password with OTP
```

#### Protected Routes (JWT Required)
```http
POST /api/auth/logout            # User logout
GET  /api/auth/profile           # Get user profile
```

#### Admin Routes (JWT + Admin Role)
```http
GET  /api/admin/users            # List all users
PUT  /api/admin/users/{id}/activate    # Activate user
PUT  /api/admin/users/{id}/deactivate  # Deactivate user
GET  /api/admin/activity-logs   # Get activity logs
GET  /api/admin/reports/users   # User reports
GET  /api/admin/reports/orders  # Order reports
GET  /api/admin/reports/activity # Activity reports
```

## 🛒 API Endpoints

### Product Management
```http
GET    /api/products             # List products (with filters)
GET    /api/products/{id}        # Get single product
POST   /api/products             # Create product (Admin)
PUT    /api/products/{id}        # Update product (Admin)
DELETE /api/products/{id}        # Delete product (Admin)
```

### Cart Management
```http
GET    /api/cart                 # Get user cart
POST   /api/cart/add             # Add item to cart
PUT    /api/cart/update/{id}     # Update cart item
DELETE /api/cart/remove/{id}     # Remove cart item
DELETE /api/cart/clear           # Clear cart
```

### Order Management
```http
GET    /api/orders               # Get user orders
GET    /api/orders/{id}          # Get single order
POST   /api/orders               # Create order
PUT    /api/orders/{id}/cancel   # Cancel order
```

### Payment Processing
```http
POST   /api/payments/initiate    # Initiate payment
POST   /api/payments/callback    # Payment callback
GET    /api/payments/status/{id} # Check payment status
```

## 📊 Database Schema

### Core Tables
- **users**: User accounts with roles
- **products**: Product catalog
- **categories**: Product categories
- **carts**: User shopping carts
- **cart_items**: Items in carts
- **orders**: Customer orders
- **order_items**: Items in orders
- **payments**: Payment records
- **activity_logs**: User activity tracking
- **password_resets**: Password reset tokens

### Relationships
- User → Cart (1:1)
- User → Orders (1:N)
- User → ActivityLogs (1:N)
- Cart → CartItems (1:N)
- Order → OrderItems (1:N)
- Order → Payment (1:1)

## 🔧 Configuration

### Queue Configuration
Configure queues in `.env`:
```env
QUEUE_CONNECTION=database
# QUEUE_CONNECTION=redis
```

### Mail Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### File Upload Configuration
```env
FILESYSTEM_DISK=public
```

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter UserTest

# Generate coverage report
php artisan test --coverage
```

### Test Structure
```
tests/
├── Feature/
│   ├── AuthTest.php
│   ├── ProductTest.php
│   ├── CartTest.php
│   └── OrderTest.php
├── Unit/
│   ├── UserTest.php
│   └── ProductTest.php
└── TestCase.php
```

## 📝 API Documentation



### Response Format
All API responses follow a consistent format:

#### Success Response
```json
{
    "success": true,
    "data": {
        // Response data
    },
    "message": "Operation successful"
}
```

#### Error Response
```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Error description",
        "details": {}
    }
}
```



### Running Queue Workers
```bash
# Start queue worker
php artisan queue:work

# Start with timeout
php artisan queue:work --timeout=60

# Process specific queue
php artisan queue:work --queue=payments
```

## 📈 Monitoring & Logging

### Activity Logging
All user actions are automatically logged:
```php
// Manual logging
activity()->causedBy($user)->log('User performed action');

// Automatic logging via middleware
```

### Log Channels
Configure in `config/logging.php`:
- **stack**: Default log channel
- **single**: Single file logging
- **daily**: Daily rotated logs
- **activity**: Activity-specific logs

## 🚀 Deployment

### Production Setup
1. **Environment Configuration**
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   ```

2. **Optimization Commands**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```





## 🔒 Security Considerations

### Implemented Security Measures
- JWT token authentication
- Input validation and sanitization
- SQL injection prevention (Eloquent ORM)
- XSS protection
- CSRF protection
- Rate limiting
- CORS configuration



## 🤝 Contributing

### Development Workflow
1. Fork the repository
2. Create feature branch
3. Make changes
4. Run tests
5. Submit pull request



## 📞 Support

### Common Issues
- **JWT Token Expired**: Refresh token or re-login
- **CORS Errors**: Check CORS configuration
- **Queue Jobs Not Running**: Start queue worker
- **Database Connection**: Verify database credentials

### Debug Mode
Enable debug mode in `.env`:
```env
APP_DEBUG=true
```

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.



---

**PrimeCart Backend API** - A robust, scalable e-commerce backend solution built with Laravel.


Developed by: Emma