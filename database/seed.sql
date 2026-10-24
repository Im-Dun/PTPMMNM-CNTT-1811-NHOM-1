INSERT INTO users (name,email,password_hash) VALUES
('Alice Editor','alice@example.test','$2y$10$tj6C2tTPM/XaFahf/dlB1uLEKDyp9Vi1Cu1tmdfj/KtR9KGMG2qsS'),
('Bob Viewer','bob@example.test','$2y$10$aa1JLgrs5NBSZKAesb4B5uM3NqodTRv2WHS1Shp2DUGvcWI.CDV.6'),
('Eve Outsider','eve@example.test','$2y$10$31wQDSlyHvrz6gBzsoSOjeHibsc5l9CmNh/exDF8VlQQfP46VvWBS');
INSERT INTO libraries (name,owner_id) VALUES ('Tài liệu học tập',1),('Thư viện riêng',1);
INSERT INTO library_members VALUES (1,1,'owner'),(1,2,'viewer'),(2,1,'owner');
INSERT INTO tags (library_id,name) VALUES (1,'PHP'),(1,'Nghiên cứu'),(1,'Đọc sau');
