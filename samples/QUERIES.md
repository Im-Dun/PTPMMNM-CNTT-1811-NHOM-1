# 10 truy vấn kiểm thử và kết quả mong đợi

Khởi động web mẫu tại `localhost:8081`, ứng dụng tại `localhost:8080`, đăng nhập Alice trừ khi ghi khác. Mỗi bài hợp lệ cần được lưu trước khi tìm.

| # | Thao tác / URL hoặc từ khóa | Kết quả mong đợi |
|---|---|---|
| 1 | Lưu `article-01.html` | Tạo snapshot v1, có tiêu đề PHP cơ bản và ảnh cục bộ. |
| 2 | Tìm `mảng kết hợp` | Có kết quả article-01 dù từ chỉ nằm trong thân bài. |
| 3 | Lưu `article-02.html` | Được lưu, dù title trùng article-01; nội dung nói prepared statement. |
| 4 | Tìm `prepared statement` | Chỉ trả article-02 đúng snapshot. |
| 5 | Lưu `article-07.html`, dừng server 8081, mở snapshot | Văn bản và ảnh GIF cục bộ vẫn hiển thị. |
| 6 | Lưu `not-found.php` | Báo lỗi HTTP 404, không tạo bài hợp lệ. |
| 7 | Lưu `empty.html` | Báo lỗi không có nội dung bài viết hợp lệ. |
| 8 | Lưu `script.html`, mở snapshot | Có “Văn bản an toàn”, không có script/alert. |
| 9 | Lưu `redirect-outside.php` | Báo chuyển hướng ra ngoài phạm vi cho phép. |
| 10 | Lưu article-09, thêm note, sửa nguồn rồi “Lấy bản mới” | Có v1 + v2; note vẫn chỉ nằm ở v1. Đăng nhập Eve và truy cập URL snapshot trả 403. |

Để mô phỏng timeout, tạm cấu hình URL về host test không phản hồi trong `config.php` (chỉ trong môi trường lab) hoặc hạ `fetch_timeout`; ứng dụng sẽ hiện lỗi riêng từ cURL và rollback giao dịch.
