-- Sample Rwandan Cultural Products
-- Run this after importing the main database.sql

USE rwaculture_db;

-- Note: These products reference images that should be uploaded to the images/ folder
-- You can use the existing images: AGASEKE1.jpg, imigongo1.jpg, BURNER.png

-- Create a demo seller when the database has no seller yet. Products must not
-- be owned by the admin because the public catalog excludes admin products.
INSERT IGNORE INTO users (email, password, first_name, last_name, phone, role, status)
VALUES ('seller@rwaculture.com', '$2y$10$Jmrw0hgTu7qYnQcVE0jX3OCshF4rSd5rGCKyMv88Yb94MilIMKE4q', 'Seller', 'Demo', '0780000001', 'seller', 'active');

SET @seller_id = (SELECT id FROM users WHERE email = 'seller@rwaculture.com' AND role = 'seller' LIMIT 1);

-- Get category IDs
SET @agaseke_cat = (SELECT id FROM categories WHERE name LIKE '%Agaseke%' LIMIT 1);
SET @imigongo_cat = (SELECT id FROM categories WHERE name LIKE '%Imigongo%' LIMIT 1);
SET @clothing_cat = (SELECT id FROM categories WHERE name LIKE '%Clothing%' LIMIT 1);
SET @jewelry_cat = (SELECT id FROM categories WHERE name LIKE '%Jewelry%' LIMIT 1);
SET @home_cat = (SELECT id FROM categories WHERE name LIKE '%Home%' LIMIT 1);

-- Insert Sample Products
INSERT INTO products (seller_id, category_id, name, description, price, stock, image, status) VALUES
-- Agaseke Baskets
(@seller_id, @agaseke_cat, 'Traditional Agaseke Basket - Large', 
'Handwoven traditional Rwandan Agaseke basket. Made from natural fibers with intricate geometric patterns. Perfect for home decoration or traditional ceremonies. Each basket is unique and crafted by skilled Rwandan artisans.', 
25000, 15, 'AGASEKE1.jpg', 'active'),

(@seller_id, @agaseke_cat, 'Small Agaseke Basket Set', 
'A set of three small Agaseke baskets in different sizes. Traditional Rwandan craftsmanship with beautiful patterns. Ideal for gifts or home decoration.', 
18000, 20, NULL, 'active'),

(@seller_id, @agaseke_cat, 'Decorative Agaseke Wall Basket', 
'Large decorative Agaseke basket designed for wall mounting. Features traditional Rwandan patterns and colors. Adds authentic cultural touch to any room.', 
35000, 8, NULL, 'active'),

-- Imigongo Art
(@seller_id, @imigongo_cat, 'Traditional Imigongo Art Panel', 
'Authentic Rwandan Imigongo art panel featuring traditional geometric patterns. Handcrafted using cow dung and natural pigments. A unique piece of Rwandan cultural heritage.', 
45000, 10, 'imigongo1.jpg', 'active'),

(@seller_id, @imigongo_cat, 'Imigongo Wall Decoration Set', 
'Set of three Imigongo art panels in different sizes. Traditional geometric patterns in black, white, and red. Perfect for creating a cultural gallery wall.', 
75000, 5, NULL, 'active'),

(@seller_id, @imigongo_cat, 'Small Imigongo Art Piece', 
'Compact Imigongo art piece perfect for small spaces. Traditional Rwandan geometric design. Handcrafted by local artisans.', 
20000, 12, NULL, 'active'),

-- Traditional Clothing
(@seller_id, @clothing_cat, 'Rwandan Traditional Dress - Umushanana', 
'Authentic Rwandan traditional dress (Umushanana) for women. Made from high-quality fabric with traditional patterns. Available in various sizes and colors.', 
85000, 10, NULL, 'active'),

(@seller_id, @clothing_cat, 'Traditional Rwandan Shirt - Men', 
'Traditional Rwandan shirt for men. Comfortable cotton fabric with cultural patterns. Perfect for ceremonies and cultural events.', 
45000, 15, NULL, 'active'),

(@seller_id, @clothing_cat, 'Rwandan Cultural Scarf', 
'Beautiful Rwandan cultural scarf with traditional patterns. Versatile accessory that can be worn in multiple ways. Made from soft, breathable fabric.', 
15000, 25, NULL, 'active'),

-- Jewelry
(@seller_id, @jewelry_cat, 'Traditional Rwandan Beaded Necklace', 
'Handcrafted traditional Rwandan beaded necklace. Colorful beads arranged in traditional patterns. A beautiful piece of cultural jewelry.', 
12000, 20, NULL, 'active'),

(@seller_id, @jewelry_cat, 'Rwandan Beaded Bracelet Set', 
'Set of three traditional Rwandan beaded bracelets. Each bracelet features different color combinations and patterns. Perfect for gifts.', 
8000, 30, NULL, 'active'),

(@seller_id, @jewelry_cat, 'Traditional Earrings - Rwandan Style', 
'Traditional Rwandan style earrings made from beads and natural materials. Lightweight and comfortable. Authentic cultural design.', 
10000, 18, NULL, 'active'),

-- Home Decor
(@seller_id, @home_cat, 'Rwandan Cultural Wall Hanging', 
'Beautiful wall hanging featuring Rwandan cultural motifs. Handwoven from natural materials. Adds warmth and cultural authenticity to any space.', 
30000, 12, NULL, 'active'),

(@seller_id, @home_cat, 'Traditional Rwandan Pottery Vase', 
'Handcrafted traditional Rwandan pottery vase. Unique design with cultural patterns. Perfect for home decoration or as a centerpiece.', 
28000, 8, NULL, 'active'),

(@seller_id, @home_cat, 'Rwandan Cultural Throw Pillow', 
'Decorative throw pillow with traditional Rwandan patterns. High-quality fabric with vibrant colors. Comfortable and stylish addition to any room.', 
18000, 15, NULL, 'active');

-- Note: After running this script, make sure to:
-- 1. Upload product images to the images/ folder
-- 2. Update image filenames in the products table to match your uploaded files
-- 3. The images AGASEKE1.jpg and imigongo1.jpg should already exist in your project folder
