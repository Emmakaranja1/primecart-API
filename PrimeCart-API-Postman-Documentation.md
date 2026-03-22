# PrimeCart API - Postman Collection Documentation

## 🚀 Base URL

**Production (Railway):** `https://web-production-e6965.up.railway.app/api`

**Local Development:** `http://localhost:8000/api`

## 🔐 Authentication Setup

### JWT Token Authentication
All protected endpoints require a JWT token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

### Getting Started
1. First, register or login to get your JWT token
2. Use the token in all subsequent requests to protected endpoints
3. Tokens expire after 60 minutes (configurable)

---

## 📋 API Endpoints

### 1. Health Check
**GET** `/health`

Check API status and health.

**Response:**
```json
{
    "status": "ok",
    "timestamp": "2024-01-01T12:00:00.000000Z",
    "service": "PrimeCart API",
    "version": "1.0.0"
}
```

---

## 🔐 Authentication Endpoints

### 2. User Registration
**POST** `/auth/register`

Register a new user account.

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "username": "johndoe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone_number": "+1234567890",
    "address": "123 Main St, City, Country"
}
```

**Response:**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "user": {
            "id": 1,
            "username": "johndoe",
            "email": "john@example.com",
            "phone_number": "+1234567890",
            "address": "123 Main St, City, Country",
            "role": "user",
            "status": "active",
            "created_at": "2024-01-01T12:00:00.000000Z",
            "updated_at": "2024-01-01T12:00:00.000000Z"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }
}
```

### 3. User Login
**POST** `/auth/login`

Authenticate user and get JWT token.

**Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "username": "johndoe",
            "email": "john@example.com",
            "role": "user",
            "status": "active"
        },
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "expires_in": 3600
    }
}
```

### 4. Admin Login
**POST** `/auth/admin/login`

Authenticate admin user.

**Body:**
```json
{
    "email": "admin@example.com",
    "password": "adminpassword"
}
```

### 5. Forgot Password
**POST** `/auth/forgot-password`

Request password reset OTP.

**Body:**
```json
{
    "email": "john@example.com"
}
```

### 6. Verify OTP
**POST** `/auth/verify-otp`

Verify OTP for password reset.

**Body:**
```json
{
    "email": "john@example.com",
    "otp": "123456"
}
```

### 7. Reset Password
**POST** `/auth/reset-password`

Reset password with verified OTP.

**Body:**
```json
{
    "email": "john@example.com",
    "otp": "123456",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

---

## 👤 Protected User Endpoints (JWT Required)

### 8. User Profile
**GET** `/auth/profile`

Get current user profile.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

### 9. Logout
**POST** `/auth/logout`

Logout user and invalidate token.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

---

## 🛍️ Product Endpoints

### 10. List Products (Public)
**GET** `/products`

Get all products with optional filtering.

**Query Parameters:**
- `page` (integer): Page number for pagination
- `limit` (integer): Items per page
- `category` (string): Filter by category
- `search` (string): Search products
- `min_price` (float): Minimum price filter
- `max_price` (float): Maximum price filter
- `sort` (string): Sort by (name, price, created_at)
- `order` (string): Sort order (asc, desc)

**Example:** `GET /products?page=1&limit=10&category=electronics&sort=price&order=asc`

**Response:**
```json
{
    "success": true,
    "data": {
        "products": [
            {
                "id": 1,
                "name": "iPhone 15 Pro",
                "description": "Latest iPhone with amazing features",
                "price": 999.99,
                "category": "electronics",
                "stock": 50,
                "image_url": "https://example.com/image.jpg",
                "status": "active",
                "created_at": "2024-01-01T12:00:00.000000Z",
                "updated_at": "2024-01-01T12:00:00.000000Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 5,
            "total_items": 50,
            "items_per_page": 10
        }
    }
}
```

### 11. Get Single Product (Public)
**GET** `/products/{id}`

Get details of a specific product.

**Path Parameters:**
- `id` (integer): Product ID

---

## 🛒 Cart Endpoints (JWT Required)

### 12. Get Cart
**GET** `/cart`

Get current user's cart items.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

### 13. Add to Cart
**POST** `/cart/add`

Add item to cart.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "product_id": 1,
    "quantity": 2
}
```

### 14. Update Cart Item
**PUT** `/cart/{id}`

Update quantity of cart item.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Path Parameters:**
- `id` (integer): Cart item ID

**Body:**
```json
{
    "quantity": 3
}
```

### 15. Remove from Cart
**DELETE** `/cart/{id}`

Remove item from cart.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

**Path Parameters:**
- `id` (integer): Cart item ID

---

## 📦 Order Endpoints (JWT Required)

### 16. Create Order
**POST** `/orders`

Create new order from cart items.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "shipping_address": "123 Main St, City, Country",
    "billing_address": "123 Main St, City, Country",
    "notes": "Please deliver after 5 PM"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "order": {
            "id": 1001,
            "user_id": 1,
            "total_amount": 1999.98,
            "status": "pending",
            "shipping_address": "123 Main St, City, Country",
            "billing_address": "123 Main St, City, Country",
            "notes": "Please deliver after 5 PM",
            "created_at": "2024-01-01T12:00:00.000000Z",
            "order_items": [
                {
                    "id": 1,
                    "product_id": 1,
                    "quantity": 2,
                    "price": 999.99,
                    "product": {
                        "name": "iPhone 15 Pro",
                        "image_url": "https://example.com/image.jpg"
                    }
                }
            ]
        }
    }
}
```

### 17. Get User Orders
**GET** `/orders`

Get all orders for current user.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

**Query Parameters:**
- `page` (integer): Page number
- `limit` (integer): Items per page
- `status` (string): Filter by status (pending, processing, shipped, delivered, cancelled)

### 18. Get Single Order
**GET** `/orders/{id}`

Get details of a specific order.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

**Path Parameters:**
- `id` (integer): Order ID

---

## 💳 Payment Endpoints (JWT Required)

### 19. Get Payment Methods
**GET** `/payment/methods`

Get available payment methods.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

**Response:**
```json
{
    "success": true,
    "data": {
        "methods": [
            {
                "id": "mpesa",
                "name": "M-PESA",
                "description": "Mobile money payment",
                "enabled": true,
                "currencies": ["KES"]
            },
            {
                "id": "flutterwave",
                "name": "Flutterwave",
                "description": "Card and bank transfer",
                "enabled": true,
                "currencies": ["USD", "EUR", "GBP", "KES"]
            },
            {
                "id": "dpo",
                "name": "DPO Group",
                "description": "African payment gateway",
                "enabled": true,
                "currencies": ["KES", "UGX", "TZS"]
            },
            {
                "id": "pesapal",
                "name": "PesaPal",
                "description": "East African payments",
                "enabled": true,
                "currencies": ["KES", "UGX", "TZS"]
            }
        ]
    }
}
```

### 20. Initiate Payment
**POST** `/payment/initiate`

Initiate payment for an order.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "order_id": 1001,
    "payment_method": "mpesa",
    "amount": 1999.98,
    "currency": "KES",
    "phone_number": "+254712345678",
    "callback_url": "https://your-app.com/payment/callback"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment initiated successfully",
    "data": {
        "payment_id": "pay_123456789",
        "status": "pending",
        "payment_url": "https://payment-gateway.com/pay/123456789",
        "reference": "ORDER1001-123456789"
    }
}
```

### 21. Verify Payment
**POST** `/payment/verify`

Verify payment status.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "payment_id": "pay_123456789",
    "reference": "ORDER1001-123456789"
}
```

### 22. Check Payment Status
**GET** `/payment/status/{id}`

Check status of a payment.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

**Path Parameters:**
- `id` (string): Payment ID

---

## 📱 M-PESA Specific Endpoints

### 23. M-PESA STK Push
**POST** `/payment/mpesa/stk-push`

Initiate M-PESA STK Push payment.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "order_id": 1001,
    "phone_number": "+254712345678",
    "amount": 1999.98,
    "account_reference": "ORDER1001"
}
```

### 24. M-PESA Status
**GET** `/payment/mpesa/status/{payment_id}`

Check M-PESA payment status.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

---

## 🌊 Flutterwave Specific Endpoints

### 25. Flutterwave Payment
**POST** `/payment/flutterwave/pay`

Initiate Flutterwave payment.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "order_id": 1001,
    "amount": 1999.98,
    "currency": "KES",
    "email": "customer@example.com",
    "phone_number": "+254712345678",
    "redirect_url": "https://your-app.com/payment/success"
}
```

### 26. Flutterwave Verify
**GET** `/payment/flutterwave/verify/{reference}`

Verify Flutterwave transaction.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

---

## 🏦 DPO Specific Endpoints

### 27. DPO Create Payment
**POST** `/payment/dpo/create`

Create DPO payment.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "order_id": 1001,
    "amount": 1999.98,
    "currency": "KES",
    "customer_email": "customer@example.com",
    "customer_name": "John Doe",
    "redirect_url": "https://your-app.com/payment/success"
}
```

### 28. DPO Verify
**POST** `/payment/dpo/verify`

Verify DPO payment.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

---

## 🇰🇪 PesaPal Specific Endpoints

### 29. PesaPal Create Payment
**POST** `/payment/pesapal/create`

Create PesaPal payment.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "order_id": 1001,
    "amount": 1999.98,
    "currency": "KES",
    "email": "customer@example.com",
    "phone_number": "+254712345678",
    "redirect_url": "https://your-app.com/payment/success"
}
```

### 30. PesaPal Status
**GET** `/payment/pesapal/status/{orderTrackingId}`

Check PesaPal payment status.

**Headers:**
```
Authorization: Bearer <your-jwt-token>
```

---

## 🔧 Admin Endpoints (JWT + Admin Role Required)

### 31. List Users (Admin)
**GET** `/admin/users`

Get all users with pagination and filtering.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

**Query Parameters:**
- `page` (integer): Page number
- `limit` (integer): Items per page
- `status` (string): Filter by status (active, inactive)
- `role` (string): Filter by role (user, admin)
- `search` (string): Search users

### 32. Activate User (Admin)
**PUT** `/admin/users/{id}/activate`

Activate a user account.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

### 33. Deactivate User (Admin)
**PUT** `/admin/users/{id}/deactivate`

Deactivate a user account.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

### 34. Get Activity Logs (Admin)
**GET** `/admin/activity-logs`

Get system activity logs.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

**Query Parameters:**
- `page` (integer): Page number
- `limit` (integer): Items per page
- `user_id` (integer): Filter by user
- `action` (string): Filter by action type
- `date_from` (string): Filter from date (Y-m-d)
- `date_to` (string): Filter to date (Y-m-d)

### 35. Create Product (Admin)
**POST** `/admin/products`

Create new product.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "name": "New Product",
    "description": "Product description",
    "price": 99.99,
    "category": "electronics",
    "stock": 100,
    "image_url": "https://example.com/image.jpg",
    "status": "active"
}
```

### 36. Update Product (Admin)
**PUT** `/admin/products/{id}`

Update existing product.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
Content-Type: application/json
```

### 37. Delete Product (Admin)
**DELETE** `/admin/products/{id}`

Delete a product.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

### 38. Get Admin Products (Admin)
**GET** `/admin/products`

Get all products with admin details.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

### 39. Get Admin Orders (Admin)
**GET** `/admin/orders`

Get all orders with admin details.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

---

## 📊 Admin Reports Endpoints

### 40. Users Report (Admin)
**GET** `/admin/reports/users`

Generate users report.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

**Query Parameters:**
- `format` (string): Export format (json, excel, pdf)
- `date_from` (string): Start date
- `date_to` (string): End date

### 41. Orders Report (Admin)
**GET** `/admin/reports/orders`

Generate orders report.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

**Query Parameters:**
- `format` (string): Export format (json, excel, pdf)
- `date_from` (string): Start date
- `date_to` (string): End date
- `status` (string): Filter by order status

### 42. Activity Report (Admin)
**GET** `/admin/reports/activity`

Generate activity report.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
```

**Query Parameters:**
- `format` (string): Export format (json, excel, pdf)
- `date_from` (string): Start date
- `date_to` (string): End date

### 43. Export Report (Admin)
**POST** `/admin/reports/export`

Export custom report.

**Headers:**
```
Authorization: Bearer <admin-jwt-token>
Content-Type: application/json
```

**Body:**
```json
{
    "type": "orders",
    "format": "excel",
    "filters": {
        "date_from": "2024-01-01",
        "date_to": "2024-12-31",
        "status": "completed"
    }
}
```

---

## 🪝 Webhook Endpoints

### 44. M-PESA Webhook
**POST** `/payment/webhooks/mpesa`

M-PESA payment webhook (no authentication).

### 45. DPO Webhook
**POST** `/payment/webhooks/dpo`

DPO payment webhook (no authentication).

### 46. PesaPal Webhook
**POST** `/payment/webhooks/pesapal`

PesaPal payment webhook (no authentication).

### 47. Flutterwave Webhook
**POST** `/payment/flutterwave/webhook`

Flutterwave payment webhook (no authentication).

---

## 🔄 Callback Endpoints

### 48. Flutterwave Callback
**GET|POST** `/payment/callbacks/flutterwave`

Flutterwave payment callback (no authentication).

---

## 📄 Payment Status Pages

### 49. Payment Success
**GET** `/payment/success`

Payment success page (no authentication).

### 50. Payment Failed
**GET** `/payment/failed`

Payment failed page (no authentication).

---

## 🧪 Test Endpoints

### 51. Test Webhook
**POST** `/test-webhook`

Test webhook functionality.

**Response:**
```json
{
    "success": true,
    "message": "Test webhook working",
    "timestamp": "2024-01-01T12:00:00.000000Z"
}
```

---

## 📝 Response Formats

### Success Response
```json
{
    "success": true,
    "data": {
        // Response data
    },
    "message": "Operation successful"
}
```

### Error Response
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

### Validation Error Response
```json
{
    "success": false,
    "message": "Validation errors",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

---

## 🔒 HTTP Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

---

## 🚀 Quick Start Guide

### 1. Setup Postman
1. Import this collection into Postman
2. Set environment variables:
   - `base_url`: `https://web-production-e6965.up.railway.app/api`
   - `jwt_token`: (will be set after login)

### 2. Register/Login Flow
1. **Register**: `POST /auth/register`
2. **Login**: `POST /auth/login`
3. **Copy the token** from login response
4. **Set `jwt_token`** environment variable

### 3. Start Using the API
1. **Browse products**: `GET /products`
2. **Add to cart**: `POST /cart/add`
3. **Create order**: `POST /orders`
4. **Make payment**: `POST /payment/initiate`

---

## 📞 Support

For any issues or questions:
- Check the API documentation
- Verify your JWT token is valid
- Ensure proper headers are set
- Check request/response formats

---

**PrimeCart API** - Complete e-commerce solution with multiple payment gateways and comprehensive admin features.

*Last Updated: January 2024*
