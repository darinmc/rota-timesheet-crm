# Rota & Timesheet CRM

A custom CRM (Customer Relationship Management) and rota system built to manage drivers, employees, and schedules.

This project evolved from an earlier Microsoft Access database, completely rebuilt as a modern, web-based application using plain PHP and MySQL.

## The Problem

Managing a roster of drivers requires tracking right-to-work checks, employee details, shift availability, and the rotas themselves. Spreadsheets and older desktop systems (like Access) are difficult to update remotely and don't offer a modern user experience. This application provides a secure, web-based CRM tailored exactly to the needs of the business.

## Tech Stack

- **Language:** PHP 8 (Plain PHP, no framework)
- **Database:** MySQL 8
- **Frontend:** Vanilla JavaScript, CSS3, HTML5
- **Architecture:** Modular PHP structure with role-based access (Admin vs. Driver views).

## Features

- **Driver & Employee Management:** Centralised database of driver details and management contacts.
- **Right-to-Work Checks:** Tracking authentication and compliance documents.
- **Rota & Timesheet System:** Creating, viewing, and managing weekly rotas.
- **Role-Based Access Control:** Secure areas for admins vs. drivers.
- **User Registration:** Complete user onboarding system with email dispatch and confirmation (deployed on public host).
- **Theming:** Per-user interface customisation.

## Deployment Notes

This system is designed to be highly portable and has been successfully deployed in two environments:
1. **Public Host (InfinityFree):** A fully tested version including the complete user registration, email dispatch, and confirmation flow.
2. **Local Ubuntu Server:** A majorly improved version currently in daily operational use, featuring advanced routing and UI improvements.

## Architecture

- `admin/`: Administrative dashboard and CRM management.
- `auth/`: Login, logout, and session handling.
- `config/`: Database and application settings (excluded from version control).
- `driver/`: The restricted view for drivers to see their shifts and details.
- `includes/`: Core logic, database connection (`db.php`), and shared functions.
- `public/`: Front-end assets and entry points.
- `templates/`: Shared layout files.

## Setup / Installation

1. Clone the repository.
2. Copy the configuration template to `config.php` and enter your MySQL database details.
3. Import the database structure.
4. Point your web server (Apache/Nginx) to the public directory.
5. Run the setup scripts to create your first admin account.
