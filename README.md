# WebShelf

Ứng dụng PHP + MySQL lưu lại nội dung các trang HTML công khai để đọc khi trang gốc đổi/mất. Mỗi lần lấy tạo một phiên bản (snapshot) bất biến; tìm kiếm, ghi chú và xuất đều gắn với đúng phiên bản đó.

## Chức năng

- Thư viện có thành viên và vai trò `owner`, `editor`, `viewer`.
- Chỉ lấy URL HTTP(S) thuộc danh sách host cho phép; kiểm tra mọi chuyển hướng.
- Tách `article`, `main` hoặc `.article-content`; từ chối 404, HTML rỗng, thông báo lỗi và timeout.
- Làm sạch HTML (không script, event handler, iframe, stylesheet/URL ngoài); tải ảnh HTTP(S) hợp lệ về cục bộ.
- Đọc snapshot cũ, làm mới tạo snapshot mới, tìm kiếm title/nội dung, nhãn, ghi chú, xuất JSON hoặc Markdown.
- Có 15 trang mẫu và 10 truy vấn kiểm thử; test case chi tiết nằm trong [docs/TEST-CASES.md](docs/TEST-CASES.md).

## Yêu cầu

- PHP 8.1+ với `pdo_mysql`, `curl`, `dom`, `mbstring`
- MySQL 8+ (hoặc MariaDB 10.5+)

## Cài đặt nhanh

1. Tạo CSDL và nạp lược đồ/dữ liệu:

   ```bash
   mysql -u root -p -e "CREATE DATABASE webshelf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   mysql -u root -p webshelf < database/schema.sql
   mysql -u root -p webshelf < database/seed.sql
   ```

2. Sao chép cấu hình và điền thông tin CSDL (không commit file này):

   ```bash
   cp config.example.php config.php
   ```

   `config.example.php` chỉ cho phép host/cổng đã khai báo. Với website công khai,
   đặt `allow_private_hosts` thành `false`; giá trị `true` trong cấu hình mẫu local
   chỉ phục vụ máy chủ fixture trên `localhost`.

3. Kiểm tra extension trước khi chạy:

   ```bash
   php -m | grep -E 'pdo_mysql|curl|dom|mbstring'
   ```

4. Chạy **hai** máy chủ terminal riêng:

   ```bash
   php -S localhost:8081 -t samples/site
   php -S localhost:8080 -t public
   ```

5. Mở `http://localhost:8080`. Đăng nhập bằng bộ chọn demo: Alice (owner/editor) hoặc Bob (viewer).

## Hai luồng demo

### 1. Lưu, đọc và tìm

Chọn Alice → mở “Lưu URL” → nhập `http://localhost:8081/article-01.html` → gắn nhãn → mở snapshot → tìm `từ khóa thân bài`. Kết quả hiển thị đoạn trích và liên kết tới snapshot đúng phiên bản.

### 2. Trang gốc thay đổi/mất

Lưu `article-02.html`, mở terminal máy chủ mẫu và sửa hoặc dừng nó. Snapshot cũ vẫn đọc được, gồm ảnh cục bộ. Bật lại máy chủ, chọn “Lấy bản mới” để tạo snapshot mới; ghi chú ở bản cũ vẫn còn. Xuất JSON chỉ được cho thành viên thư viện.

## Kiểm thử bắt buộc

Các URL mẫu và kết quả mong đợi nằm trong [samples/QUERIES.md](samples/QUERIES.md). Các trường hợp `404.html`, `empty.html`, `script.html` và `redirect-outside.php` kiểm tra lỗi/khóa truy cập. Với `script.html`, snapshot hợp lệ nhưng đoạn script không bao giờ được đưa vào HTML đã lưu.

## Cấu trúc

```
public/        giao diện và router HTTP
src/           xử lý đăng nhập demo, quyền, fetch/làm sạch/lưu trữ
database/      schema và dữ liệu giả
samples/site/  trang nguồn tĩnh phục vụ demo
```

## Giới hạn chủ ý

- Chỉ hỗ trợ HTML tĩnh, bố cục `article`, `main`, `.article-content`, và ảnh `jpg/jpeg/png/gif/webp`.
- Không vượt đăng nhập, cookie, paywall; không tải CSS, JS, iframe, video hay tài nguyên ngoài khác.
- Danh sách host mặc định chỉ có `localhost`/`127.0.0.1`, phù hợp dữ liệu giả trong bài nộp. Muốn dùng host khác phải chủ động thêm vào `config.php`.

## Mã và tài nguyên tái sử dụng

- Mã ứng dụng trong `src/` và `public/` do nhóm viết, dùng PHP standard library/extensions nêu trên, không có dependency bên thứ ba.
- Nội dung, ảnh SVG mẫu trong `samples/` là dữ liệu giả do nhóm tạo.

Xem [CONTRIBUTING.md](CONTRIBUTING.md) để cộng tác và [LICENSE](LICENSE) để biết giấy phép.
