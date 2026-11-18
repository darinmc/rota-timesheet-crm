
## Sample Documentation Files

### 1. **docs/README.md** (Project Overview)

```markdown
# STS Transport System

## Overview
A web-based transport management system for Sheffield Travel Support.

## Features
- ✅ Passenger management
- ✅ Location management
- ✅ Driver management
- 🚧 Rota scheduling
- 🚧 Timesheet reporting

## Tech Stack
- PHP 8.x
- MySQL
- HTML5/CSS3
- JavaScript (Vanilla)

## Modules
| Module | Status | Description |
|--------|--------|-------------|
| Passengers | ✅ Complete | CRUD operations with search |
| Locations | ✅ Complete | CRUD operations with search |
| Drivers | 🚧 In Progress | Driver management |
| Rota | 📅 Planned | Weekly scheduling system |

## Database Tables
- `users` - System users (admins & drivers)
- `passengers` - Passenger information
- `locations` - Pickup/dropoff locations
- `taxi_jobs_driver` - Scheduled jobs

## Recent Changes
See [CHANGELOG.md](development/changelog.md)