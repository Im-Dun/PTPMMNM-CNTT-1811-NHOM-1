# Mô hình dữ liệu

`database/schema.sql` dùng các bảng:

- `users`, `libraries`, `library_members`: người dùng, thư viện và quyền owner/editor/viewer.
- `saved_pages`: bài viết logic thuộc một thư viện và URL nguồn.
- `snapshots`: nội dung bất biến theo `version_no`, metadata fetch, plain text và FULLTEXT.
- `assets`: ảnh local gắn đúng snapshot.
- `tags`, `saved_page_tags`: nhãn theo thư viện và quan hệ nhiều-nhiều.
- `notes`: ghi chú của người dùng gắn với snapshot, không phải bài viết chung.

Snapshot và asset dùng khóa ngoại cascade theo bài lưu; lịch sử version không bị thay
thế khi refresh. Các thao tác tạo snapshot/asset phải nằm trong transaction của use case.
