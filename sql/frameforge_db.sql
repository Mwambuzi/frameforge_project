-- =========================================================================
-- FRAMEFORGE DATABASE IMPLEMENTATION SCRIPT (DELIVERABLE 3)
-- DBMS: MySQL / MariaDB (InnoDB, utf8mb4, 3NF)
-- =========================================================================

DROP DATABASE IF EXISTS frameforge_db;
CREATE DATABASE frameforge_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE frameforge_db;

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id)
        REFERENCES roles(role_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE genres (
    genre_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB;

CREATE TABLE projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    creator_id INT NOT NULL,
    genre_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    synopsis TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_projects_creator (creator_id),
    INDEX idx_projects_genre (genre_id),
    INDEX idx_projects_status (status),
    CONSTRAINT chk_projects_status CHECK (status IN ('draft', 'in_production', 'pending_review', 'published', 'archived')),
    CONSTRAINT chk_projects_price CHECK (price >= 0.00),
    CONSTRAINT fk_projects_creator FOREIGN KEY (creator_id)
        REFERENCES users(user_id) ON DELETE RESTRICT,
    CONSTRAINT fk_projects_genre FOREIGN KEY (genre_id)
        REFERENCES genres(genre_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE storyboard_panels (
    panel_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    sequence_no INT NOT NULL,
    scene_description TEXT NOT NULL,
    sketch_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project_sequence (project_id, sequence_no),
    INDEX idx_panels_project (project_id),
    CONSTRAINT fk_panels_project FOREIGN KEY (project_id)
        REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ai_jobs (
    job_id INT AUTO_INCREMENT PRIMARY KEY,
    panel_id INT NOT NULL,
    requested_by INT NOT NULL,
    prompt TEXT NOT NULL,
    model_name VARCHAR(50) DEFAULT 'FrameForge-Interp-v2',
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_ai_jobs_panel (panel_id),
    INDEX idx_ai_jobs_user (requested_by),
    CONSTRAINT chk_ai_jobs_status CHECK (status IN ('queued', 'processing', 'completed', 'failed')),
    CONSTRAINT fk_ai_jobs_panel FOREIGN KEY (panel_id)
        REFERENCES storyboard_panels(panel_id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_jobs_user FOREIGN KEY (requested_by)
        REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE frames (
    frame_id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_approved BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_frames_job (job_id),
    CONSTRAINT fk_frames_job FOREIGN KEY (job_id)
        REFERENCES ai_jobs(job_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE content_items (
    content_id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content_type VARCHAR(20) NOT NULL,
    video_url VARCHAR(255) NOT NULL,
    duration_seconds INT,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_content_project (project_id),
    CONSTRAINT chk_content_type CHECK (content_type IN ('snippet', 'full')),
    CONSTRAINT chk_content_duration CHECK (duration_seconds > 0),
    CONSTRAINT fk_content_project FOREIGN KEY (project_id)
        REFERENCES projects(project_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    INDEX idx_orders_user (user_id),
    CONSTRAINT chk_orders_status CHECK (status IN ('pending', 'paid', 'failed', 'refunded')),
    CONSTRAINT chk_orders_total CHECK (total_amount >= 0.00),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    project_id INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    UNIQUE KEY uq_order_project (order_id, project_id),
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_project (project_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_project FOREIGN KEY (project_id)
        REFERENCES projects(project_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    transaction_ref VARCHAR(50) UNIQUE,
    status VARCHAR(20) NOT NULL,
    INDEX idx_payments_order (order_id),
    CONSTRAINT chk_payments_status CHECK (status IN ('pending', 'success', 'failed')),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id)
        REFERENCES orders(order_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO roles (role_id, role_name) VALUES
(1, 'viewer'),
(2, 'animator'),
(3, 'admin'),
(4, 'super_admin');

INSERT INTO users (user_id, role_id, username, email, password_hash, is_active) VALUES
(1, 4, 'lincon_admin', 'lincon.mwambu@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE),
(2, 2, 'carldon_anim', 'carldon.kenyoru@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE),
(3, 1, 'benson_viewer', 'benson.ndolo@strathmore.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE);

INSERT INTO genres (genre_id, name, description) VALUES
(1, 'Afro-SciFi', 'Futuristic African cyberpunk and speculative science fiction animations.'),
(2, '2D Action', 'High-energy stylized 2D martial arts and adventure sequences.'),
(3, 'Comedy & Slice of Life', 'Lighthearted everyday stories and character-driven humor.');

INSERT INTO projects (project_id, creator_id, genre_id, title, synopsis, status, price) VALUES
(1, 2, 1, 'Neon Nairobi 2089', 'A young courier navigates flying matatus and rogue AI drones across futuristic Nairobi.', 'published', 150.00),
(2, 2, 2, 'Rift Valley Chronicles', 'Ancient guardians awaken in the Great Rift to defend sacred geothermal shrines.', 'published', 200.00),
(3, 2, 3, 'Campus Rush', 'Three computer science students race against the clock before mid-semester project marking.', 'pending_review', 100.00);

INSERT INTO storyboard_panels (panel_id, project_id, sequence_no, scene_description, sketch_url) VALUES
(1, 1, 1, 'Wide shot of Nairobi skyline at night with neon matatu hologram trails.', 'uploads/sketches/neon_panel_1.png'),
(2, 1, 2, 'Close-up of protagonist adjusting cybernetic goggles on a rooftop.', 'uploads/sketches/neon_panel_2.png'),
(3, 3, 1, 'Overhead shot of lab desks glowing with dual monitors at 2 AM.', 'uploads/sketches/campus_panel_1.png');

INSERT INTO ai_jobs (job_id, panel_id, requested_by, prompt, model_name, status, completed_at) VALUES
(1, 1, 2, '24fps anime interpolation, neon rain reflections, sweeping camera pan', 'FrameForge-Interp-v2', 'completed', NOW()),
(2, 2, 2, 'Smooth hair wind motion and glowing HUD visor reflection', 'FrameForge-Interp-v2', 'completed', NOW());

INSERT INTO frames (frame_id, job_id, image_url, is_approved) VALUES
(1, 1, 'uploads/frames/job1_frame_001.png', TRUE),
(2, 1, 'uploads/frames/job1_frame_002.png', TRUE),
(3, 2, 'uploads/frames/job2_frame_001.png', FALSE);

INSERT INTO content_items (content_id, project_id, title, content_type, video_url, duration_seconds, is_published) VALUES
(1, 1, 'Neon Nairobi - Free Teaser Snippet', 'snippet', 'https://www.w3schools.com/html/mov_bbb.mp4', 30, TRUE),
(2, 1, 'Neon Nairobi - Episode 1 (Full Film)', 'full', 'https://www.w3schools.com/html/mov_bbb.mp4', 720, TRUE),
(3, 2, 'Rift Valley Chronicles - Teaser Snippet', 'snippet', 'https://www.w3schools.com/html/mov_bbb.mp4', 35, TRUE),
(4, 2, 'Rift Valley Chronicles - Episode 1 (Full)', 'full', 'https://www.w3schools.com/html/mov_bbb.mp4', 900, TRUE);

INSERT INTO orders (order_id, user_id, status, total_amount) VALUES
(1, 3, 'paid', 150.00);

INSERT INTO order_items (order_item_id, order_id, project_id, unit_price) VALUES
(1, 1, 1, 150.00);

INSERT INTO payments (payment_id, order_id, amount, payment_method, transaction_ref, status) VALUES
(1, 1, 150.00, 'M-Pesa', 'SJB74K92LM', 'success');

INSERT INTO audit_logs (user_id, action, table_name, record_id, old_value, new_value) VALUES
(1, 'APPROVE_PROJECT', 'projects', 1, 'status=pending_review, price=0.00', 'status=published, price=150.00'),
(3, 'MPESA_PAYMENT_SUCCESS', 'orders', 1, 'status=pending', 'status=paid, ref=SJB74K92LM');
