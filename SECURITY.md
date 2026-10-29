# Security policy

## Phạm vi

WebArchive chỉ được thiết kế cho HTML công khai thuộc host đã được người vận hành
cho phép. Không dùng ứng dụng để vượt paywall, đăng nhập, robots policy hoặc lưu
nội dung mà người vận hành không có quyền sử dụng.

## Báo cáo lỗ hổng

Không đưa thông tin khai thác hoặc dữ liệu nhạy cảm lên issue công khai. Gửi báo cáo
riêng cho người bảo trì repository, gồm phiên bản, bước tái hiện tối thiểu, tác động
và đề xuất khắc phục. Không commit `config.php`, mật khẩu, token, dữ liệu người dùng
hoặc file upload vào repository.

## Các biện pháp hiện có

- URL, scheme, cổng, DNS/IP và mọi redirect được kiểm tra trước khi fetch.
- Response có giới hạn thời gian và kích thước; cURL không tự theo redirect.
- HTML được parse bằng DOMDocument; script, event handler, iframe, form và tài nguyên
  không hỗ trợ bị loại bỏ trước khi lưu.
- Snapshot và asset chỉ được truy cập sau khi kiểm tra membership ở backend.
- Truy vấn database dùng prepared statement; form POST dùng CSRF token.
- `allow_private_hosts` chỉ bật cho fixture local trong môi trường demo, không bật khi
  triển khai host công khai.
