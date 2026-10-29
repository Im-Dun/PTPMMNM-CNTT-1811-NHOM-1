INSERT INTO users (name,email) VALUES ('Alice Editor','alice@example.test'),('Bob Viewer','bob@example.test'),('Eve Outsider','eve@example.test');
INSERT INTO libraries (name,owner_id) VALUES ('Tài liệu học tập',1),('Thư viện riêng',1);
INSERT INTO library_members VALUES (1,1,'owner'),(1,2,'viewer'),(2,1,'owner');
INSERT INTO tags (library_id,name) VALUES (1,'PHP'),(1,'Nghiên cứu'),(1,'Đọc sau');
