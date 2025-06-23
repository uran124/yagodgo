CREATE TABLE content_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    alias VARCHAR(255) NOT NULL,
    UNIQUE KEY alias (alias)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    short_desc TEXT,
    text TEXT,
    meta_title VARCHAR(255),
    meta_description VARCHAR(255),
    meta_keywords VARCHAR(255),
    product1_id INT DEFAULT NULL,
    product2_id INT DEFAULT NULL,
    product3_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES content_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
