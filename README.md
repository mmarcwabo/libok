# Libok - A Clean Architecture PHP Project

**Libok** is a PHP project boilerplate built on the principles of Clean Architecture. It comes with ready-to-use features like user authentication and management, using Doctrine ORM and Bootstrap 5 fot UI.

## Features

-   **Clean Architecture**: Separation of concerns into Domain, Application, Infrastructure, and Framework layers.
-   **Doctrine ORM**: Powerful database abstraction layer for robust data management.
-   **User Management**: Full CRUD functionality for users (Login, Registration, List, Create, Edit, Delete).
-   **Bootstrap 5 UI**: Clean and responsive views.
-   **PSR-4 Autoloading**: Standardized project structure managed by Composer.
-   **Environment-based Configuration**: Uses `.env` files for secure and flexible configuration.
-   **Testing Suite**: Includes templates for Unit and Integration tests with PHPUnit.

## Requirements

-   PHP >= 8.1
-   Composer
-   A database server (e.g., MySQL, MariaDB, PostgreSQL)

## Installation Guide

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/mmarcwabo/libok.git

    cd libok
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Configure your environment:**
    Copy the example environment file and update it with your database credentials.
    ```bash
    cp .env.example .env
    ```
    Now, open `.env` and edit the `DB_*` variables.

4.  **Create the database schema:**
    Run the Doctrine command-line tool to create the necessary tables in your database.
    ```bash
    composer schema-create
    ```

## Running the Project

Use PHP's built-in web server to run the application locally.

1.  **Start the server:**
    Run this command from the project's root directory.
    ```bash
    composer serve
    ```
    This is equivalent to:
    ```bash
    php -S localhost:8000 -t public
    ```

2.  **Access the application:**
    Open your web browser and navigate to `http://localhost:8000`.

## Running Tests

The project is configured to use an in-memory SQLite database for testing, so no additional setup is required.

To run the full test suite (Unit and Integration tests), execute:

```bash
composer test