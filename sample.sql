-- Create Database
CREATE DATABASE IF NOT EXISTS sample;
USE sample;

-- =========================================
-- USERS TABLE
-- =========================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- PRODUCTS TABLE
-- =========================================
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_quantity INT NOT NULL DEFAULT 0,
    sku VARCHAR(100) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        ON UPDATE CURRENT_TIMESTAMP
);

-- =========================================
-- ORDERS TABLE
-- =========================================
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM(
        'pending',
        'processing',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
);

-- =========================================
-- ORDER ITEMS TABLE
-- =========================================
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders(order_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id)
        REFERENCES products(product_id)
        ON DELETE CASCADE
);

-- =========================================
-- SAMPLE DATA
-- =========================================

INSERT INTO users (
    first_name,
    last_name,
    email,
    password,
    phone,
    address
) VALUES
(
    'John',
    'Doe',
    'john@example.com',
    'hashedpassword123',
    '09171234567',
    'Manila, Philippines'
),
(
    'Jane',
    'Smith',
    'jane@example.com',
    'hashedpassword456',
    '09981234567',
    'Quezon City, Philippines'
);

INSERT INTO products (
    product_name,
    description,
    price,
    stock_quantity,
    sku
) VALUES
(
    'Laptop',
    '15-inch business laptop',
    45000.00,
    10,
    'LAP-001'
),
(
    'Wireless Mouse',
    'Bluetooth wireless mouse',
    850.00,
    50,
    'MOU-001'
);

INSERT INTO orders (
    user_id,
    total_amount,
    status
) VALUES
(
    1,
    45850.00,
    'completed'
);

INSERT INTO order_items (
    order_id,
    product_id,
    quantity,
    price
) VALUES
(
    1,
    1,
    1,
    45000.00
),
(
    1,
    2,
    1,
    850.00
);