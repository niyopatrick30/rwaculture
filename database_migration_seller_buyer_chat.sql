-- Add direct buyer-to-seller live chat support to an existing database.
USE rwaculture_db;

ALTER TABLE chats
    ADD COLUMN seller_id INT NULL AFTER user_id,
    ADD INDEX idx_chats_seller (seller_id),
    ADD CONSTRAINT fk_chats_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL;
