# Login Form Project

## Overview
This project is a secure login form application built using HTML, CSS, JavaScript, PHP, and MySQL. It provides a user-friendly interface for users to log in, with features such as "Remember Me" and password reset functionality. The application adheres to best practices for security, including input validation, password hashing, and protection against SQL injection and XSS attacks.

## Project Structure
```
login-form-project
├── assets
│   ├── css
│   │   └── style.css          # CSS styles for the login form
│   └── js
│       └── validation.js      # JavaScript for client-side validation
├── config
│   └── database.php           # Database connection setup
├── includes
│   ├── auth.php               # User authentication functions
│   ├── functions.php          # Utility functions for input sanitization
│   └── session.php            # Session management
├── public
│   ├── index.php              # Entry point of the application
│   ├── login.php              # Login form and processing
│   ├── logout.php             # Logout functionality
│   └── reset-password.php      # Password reset functionality
├── sql
│   └── users.sql              # SQL script to create users table
├── .htaccess                  # Server configuration settings
└── README.md                  # Project documentation
```

## Features
- **Responsive Design**: The login form is styled to be modern and responsive, ensuring usability across various devices.
- **Client-side Validation**: JavaScript is used to validate user input before submission, providing immediate feedback.
- **Secure Authentication**: PHP handles user authentication securely, with password hashing and session management.
- **Database Interaction**: PDO is used for secure database interactions, preventing SQL injection attacks.
- **Session Security**: Sessions are managed securely to prevent hijacking and ensure user data protection.

## Setup Instructions
1. **Clone the Repository**: Download or clone the project repository to your local machine.
2. **Database Configuration**: Update the `config/database.php` file with your database credentials.
3. **Create Database**: Run the SQL script located in `sql/users.sql` to create the necessary users table in your MySQL database.
4. **Access the Application**: Open `public/index.php` in your web browser to access the application.

## Usage
- Users can log in using their email or username and password.
- The "Remember Me" option allows users to stay logged in on their devices.
- If users forget their password, they can use the password reset functionality to receive a reset link.

## Security Practices
- **Input Validation**: All user inputs are validated and sanitized to prevent XSS attacks.
- **Password Hashing**: User passwords are hashed before being stored in the database using secure hashing algorithms.
- **Prepared Statements**: PDO prepared statements are used for all database queries to prevent SQL injection.

## License
This project is open-source and available for modification and distribution under the MIT License.