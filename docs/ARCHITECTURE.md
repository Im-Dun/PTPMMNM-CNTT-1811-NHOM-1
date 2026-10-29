# Kiến trúc hiện tại

## Luồng request

`public/index.php` là front controller/router. `src/bootstrap.php` khởi tạo session,
kiểm tra extension, đọc cấu hình PDO và nạp helper. `src/functions.php` giữ các helper
trình bày, CSRF, session và authorization; `src/Fetcher.php` xử lý pipeline fetch,
parse, sanitize và local asset.

## Ranh giới thành phần

- **Presentation:** `public/index.php`, `public/assets/styles.css`.
- **Application:** các use case hiện nằm trong router; khi mở rộng sẽ tách các luồng
  fetch, search, export và versioning thành service nhỏ.
- **Domain:** quy tắc role, snapshot bất biến, version number và snapshot-scoped note.
- **Infrastructure:** PDO/MySQL trong `bootstrap.php`, HTTP/DOM trong `Fetcher.php`,
  file asset trong `public/uploads`.

## Nguyên tắc

Mọi truy vấn lấy page, snapshot, asset, note hoặc export phải nối qua library membership
và kiểm tra role ở backend. Snapshot chỉ được đánh dấu thành công sau extraction và
persist; refresh tạo version mới thay vì ghi đè version cũ. Reader chỉ render
`clean_html` đã lưu và URL asset local.
