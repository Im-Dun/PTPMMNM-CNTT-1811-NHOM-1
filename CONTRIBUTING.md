# Contributing

1. Tạo issue mô tả lỗi/đề xuất và dữ liệu giả để tái hiện.
2. Tạo nhánh nhỏ theo issue, không commit `config.php`, database dump thật hay URL riêng tư.
3. Chạy các truy vấn ở `samples/QUERIES.md`, kiểm tra phân quyền bằng cả Alice và Bob.
4. Mở pull request, nêu rõ luồng demo đã thử. Một thành viên khác review về quyền, URL/redirect và HTML không an toàn trước khi merge.

Mọi thay đổi fetcher phải giữ nguyên: validate URL ban đầu **và từng redirect**, không chạy script và không để snapshot phụ thuộc tài nguyên ngoài.
