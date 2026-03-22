# PrimeCart API - Postman Setup Guide

## 📋 Files Created

1. **PrimeCart-API-Postman-Documentation.md** - Complete API documentation
2. **PrimeCart-API-Postman-Collection.json** - Postman collection file
3. **PrimeCart-API-Postman-Environment.json** - Postman environment file
4. **Postman-Setup-Guide.md** - This setup guide

## 🚀 Quick Setup

### Step 1: Import Collection & Environment

1. Open Postman
2. Click **Import** in the top left
3. Select **File** tab
4. Upload `PrimeCart-API-Postman-Collection.json`
5. Upload `PrimeCart-API-Postman-Environment.json`

### Step 2: Select Environment

1. In Postman's top-right dropdown, select **"PrimeCart API Environment"**
2. Verify the environment variables are loaded

### Step 3: Test the API

#### Health Check
1. Open the **"Health Check"** request
2. Click **Send**
3. You should see a success response

#### Authentication Flow
1. **Register User**: Open `Authentication > Register` and send
2. **Login**: Open `Authentication > Login` and send
   - The JWT token will be automatically saved to your environment
3. **Test Protected Endpoint**: Open `Authentication > Profile` to verify authentication

## 🔧 Environment Variables

The collection uses these environment variables:

| Variable | Value | Description |
|----------|-------|-------------|
| `base_url` | `https://web-production-e6965.up.railway.app/api` | Railway production URL |
| `local_base_url` | `http://localhost:8000/api` | Local development URL |
| `jwt_token` | *(auto-populated)* | Authentication token |
| `user_email` | `john@example.com` | Test user email |
| `user_password` | `password123` | Test user password |
| `admin_email` | `admin@example.com` | Test admin email |
| `admin_password` | `adminpassword` | Test admin password |
| `test_phone` | `+254712345678` | Test phone number |
| `test_product_id` | `1` | Test product ID |
| `test_order_id` | `1` | Test order ID |

## 🔄 Switching Between Environments

### For Local Development
1. Edit the environment
2. Change `base_url` to `{{local_base_url}}`
3. Save and use

### For Production
1. Edit the environment  
2. Change `base_url` to `https://web-production-e6965.up.railway.app/api`
3. Save and use

## 📱 Testing Payment Gateways

### M-PESA Testing
1. Login and get JWT token
2. Add products to cart
3. Create an order
4. Use `M-PESA > M-PESA STK Push` to initiate payment
5. Check status with `M-PESA > M-PESA Status`

### Flutterwave Testing
1. Follow the same flow as M-PESA
2. Use `Flutterwave > Flutterwave Payment` endpoint
3. Verify with `Flutterwave > Flutterwave Verify`

## 🔐 Admin Features

1. Login as admin using `Authentication > Admin Login`
2. Access admin endpoints:
   - Admin > List Users
   - Admin > Get Activity Logs
   - Admin > Create Product
   - Reports > Users Report
   - Reports > Orders Report

## 🧪 Testing Tips

### Authentication
- Always login first to get a fresh JWT token
- The collection automatically saves the token after successful login
- Token expires after 60 minutes

### Error Handling
- Check response codes and messages
- 401 errors usually mean expired token - login again
- 422 errors indicate validation issues - check request body

### Webhook Testing
- Use `Webhooks & Callbacks > Test Webhook` to verify connectivity
- Webhook endpoints don't require authentication

## 🐛 Common Issues

### JWT Token Issues
- **Problem**: 401 Unauthorized errors
- **Solution**: Login again to get a fresh token

### CORS Issues
- **Problem**: CORS errors in browser
- **Solution**: Use Postman instead of browser for API testing

### Connection Issues
- **Problem**: Cannot connect to API
- **Solution**: 
  - Check your internet connection
  - Verify the `base_url` is correct
  - Try the health check endpoint first

## 📚 API Documentation

Refer to `PrimeCart-API-Postman-Documentation.md` for:
- Complete endpoint documentation
- Request/response examples
- Authentication details
- Error handling

## 🚀 Production URL

**Railway Deployed URL**: `https://web-production-e6965.up.railway.app/api`

## 📞 Support

If you encounter issues:
1. Check the API documentation
2. Verify environment variables
3. Test with the health check endpoint
4. Check request/response formats

---

**Happy Testing! 🎉**
