# Invoice Management System

## Project Overview
This project is a web application for managing invoices. It utilizes a modern tech stack with a frontend, backend, and database to handle invoice creation, tracking, and reporting.

## Tech Stack
- Frontend: Nuxt.js (Vue.js framework)
- Backend: Laravel (PHP)
- Database: MySQL
- Caching: Redis
- Containerization: Docker

## Getting Started
1. Clone the repository
2. Install Docker and Docker Compose
3. Run `docker-compose up -d` to start all services
4. Access the frontend at `http://localhost:80`
5. Access the backend API at `http://localhost:8000`
6. Access Adminer (database management) at `http://localhost:8080`

## Project Structure
- `/client`: Frontend Nuxt.js application
- `/backend`: Laravel backend application
- `/nginx`: Nginx configuration for reverse proxy
- `docker-compose.yml`: Docker Compose configuration file
- `.env`: Environment variables (make sure to set up your own)

## Development
- Frontend development server runs on port 3000
- Backend API runs on port 8000
- MySQL database runs on port 3306
- Redis cache runs on port 6379

## Deployment
The application is containerized using Docker, making it easy to deploy to various environments. Ensure all environment variables are properly set in the `.env` file before deployment.

## Contributing
Please read CONTRIBUTING.md for details on our code of conduct and the process for submitting pull requests.

## License
This project is licensed under the MIT License - see the LICENSE.md file for details