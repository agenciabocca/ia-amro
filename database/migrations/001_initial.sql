CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL UNIQUE,
    nome VARCHAR(255),
    cliente_id_bling BIGINT NULL,
    cliente_id_wc BIGINT NULL,
    ia_paused TINYINT(1) DEFAULT 0,
    unsubscribed_at TIMESTAMP NULL,
    last_message_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_paused (ia_paused),
    INDEX idx_last_msg (last_message_at)
);

CREATE TABLE IF NOT EXISTS chat_memory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(20) NOT NULL,
    role VARCHAR(20) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    tool_calls JSON NULL,
    tool_call_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (session_id, created_at)
);

CREATE TABLE IF NOT EXISTS messages_buffer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id VARCHAR(20) NOT NULL,
    message TEXT,
    message_id VARCHAR(255),
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact (contact_id),
    INDEX idx_received (received_at)
);

CREATE TABLE IF NOT EXISTS conversation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    direction VARCHAR(20),
    message MEDIUMTEXT,
    ai_action VARCHAR(50) NULL,
    tool_calls JSON NULL,
    tokens_input INT DEFAULT 0,
    tokens_output INT DEFAULT 0,
    model VARCHAR(50) NULL,
    latency_ms INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_action (ai_action),
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS processed_messages (
    message_id VARCHAR(255) PRIMARY KEY,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS config (
    config_key VARCHAR(100) PRIMARY KEY,
    config_value LONGTEXT,
    version INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS error_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20),
    error_type VARCHAR(80),
    error_message TEXT,
    context JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (error_type),
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS unsubscribed_phones (
    phone VARCHAR(20) PRIMARY KEY,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS bling_token (
    id TINYINT PRIMARY KEY DEFAULT 1,
    access_token TEXT,
    refresh_token VARCHAR(255),
    expires_at TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
