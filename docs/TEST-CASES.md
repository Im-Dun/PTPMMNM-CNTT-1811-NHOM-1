# Test cases

| ID | Kịch bản | Kết quả mong đợi |
|---|---|---|
| T01 | Lưu bài có ảnh, dừng fixture server, mở reader | Text và ảnh local vẫn đọc được. |
| T02 | Tìm từ chỉ có trong thân bài | Kết quả trỏ đúng snapshot và snippet chứa từ khóa. |
| T03 | Lưu 404, empty và timeout | Hiện lỗi riêng; không có snapshot hợp lệ mới. |
| T04 | Lưu script/event/iframe và URL bị cấm | Nội dung thực thi bị loại; URL/redirect cấm bị từ chối. |
| T05 | Refresh bài, ghi chú v1, truy cập bằng Eve | Có v1/v2; note v1 còn; Eve nhận 403 và không export được. |
| T06 | Owner thêm Bob với quyền editor, thêm nhiều nhãn và gán cho bài | Bob lưu/refresh được; nhãn hiển thị và lọc được; viewer không lưu/refresh được. |
| T07 | Tìm từ chỉ có trong tiêu đề và lọc theo nguồn/nhãn | Kết quả vẫn có snippet hữu ích và chỉ thuộc các thư viện người dùng được cấp quyền. |
| T08 | Owner xóa thành viên hoặc nhãn | Thành viên mất quyền ngay; xóa nhãn không xóa bài hoặc snapshot. |

Fixture URL và 10 truy vấn mẫu nằm trong `samples/QUERIES.md`. Integration test cần
MySQL, `pdo_mysql`, `curl`, `dom` và `mbstring`; môi trường thiếu extension phải được
hiển thị là blocker, không được đánh dấu PASS giả.
