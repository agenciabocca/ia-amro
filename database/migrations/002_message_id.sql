ALTER TABLE conversation_logs ADD COLUMN message_id VARCHAR(255) NULL AFTER direction;
ALTER TABLE conversation_logs ADD INDEX idx_msgid (message_id);
