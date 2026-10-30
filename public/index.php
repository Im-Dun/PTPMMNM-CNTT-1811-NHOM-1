<?php
require dirname(__DIR__) . '/src/bootstrap.php';

// Đồng bộ tham số routing ($r) từ cả 'r' hoặc 'page'
$r = $_GET['r'] ?? $_GET['page'] ?? 'home';

require dirname(__DIR__) . '/src/routes_extra.php';

// -----------------------------------------------------------------------------
// 1. XỬ LÝ LOGIC BACKEND (ROUTING & ACTIONS)
// -----------------------------------------------------------------------------

// TRANG DANG NHAP
if ($r === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $s = $pdo->prepare('SELECT id,name,email,password_hash FROM users WHERE email=?');
        $s->execute([$email]);
        $account = $s->fetch();
        if (!$account || !password_verify($password, (string)$account['password_hash'])) {
            $error = 'Email hoặc mật khẩu không đúng.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $account['id'], 'name' => $account['name'], 'email' => $account['email']];
            header('Location: ?r=home');
            exit;
        }
    }
    $title_page = 'Đăng nhập';
    ob_start();
    ?>
    <section class="shell py-16">
      <div class="surface max-w-xl mx-auto p-8 sm:p-11">
        <p class="eyebrow mb-3">WEBSHELF / TÀI KHOẢN</p>
        <h1 class="text-[38px] mb-4 font-semibold">Đăng nhập</h1>
        <?php if (isset($error)): ?>
            <p class="p-3 mb-4 rounded bg-red-100 text-red-700 text-sm font-semibold"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <div>
            <label class="block font-bold mb-1">Email</label>
            <input type="email" name="email" class="field" required autocomplete="email">
          </div>
          <div>
            <label class="block font-bold mb-1">Mật khẩu</label>
            <input type="password" name="password" class="field" required autocomplete="current-password">
          </div>
          <button type="submit" class="btn btn-primary w-full">Đăng nhập</button>
        </form>
        <p class="text-sm text-[#607579] mt-6">Chưa có tài khoản? <a href="?r=register" class="text-[#17565a] font-bold underline">Đăng ký ngay</a></p>
      </div>
    </section>
    <?php
    $content_page = ob_get_clean();
}

// TRANG DANG KY
elseif ($r === 'register') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        if ($name === '' || mb_strlen($name) > 80) {
            $error = 'Tên phải có từ 1 đến 80 ký tự.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            $error = 'Email không hợp lệ.';
        } elseif (strlen($password) < 8) {
            $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
        } elseif ($password !== $confirmation) {
            $error = 'Mật khẩu xác nhận không khớp.';
        } else {
            try {
                $insert = $pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user'] = ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'email' => $email];
                header('Location: ?r=home');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Email này đã được đăng ký.';
                } else {
                    throw $e;
                }
            }
        }
    }
    $title_page = 'Đăng ký';
    ob_start();
    ?>
    <section class="shell py-16">
      <div class="surface max-w-xl mx-auto p-8 sm:p-11">
        <p class="eyebrow mb-3">WEBSHELF / TÀI KHOẢN</p>
        <h1 class="text-[38px] mb-4 font-semibold">Tạo tài khoản</h1>
        <?php if (isset($error)): ?>
            <p class="p-3 mb-4 rounded bg-red-100 text-red-700 text-sm font-semibold"><?= h($error) ?></p>
        <?php endif; ?>
        <form method="post" class="space-y-4">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <div>
            <label class="block font-bold mb-1">Họ tên</label>
            <input name="name" class="field" required maxlength="80" autocomplete="name">
          </div>
          <div>
            <label class="block font-bold mb-1">Email</label>
            <input type="email" name="email" class="field" required maxlength="120" autocomplete="email">
          </div>
          <div>
            <label class="block font-bold mb-1">Mật khẩu</label>
            <input type="password" name="password" class="field" required minlength="8" autocomplete="new-password">
          </div>
          <div>
            <label class="block font-bold mb-1">Nhập lại mật khẩu</label>
            <input type="password" name="password_confirmation" class="field" required minlength="8" autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-primary w-full">Tạo tài khoản</button>
        </form>
        <p class="text-sm text-[#607579] mt-6">Đã có tài khoản? <a href="?r=login" class="text-[#17565a] font-bold underline">Đăng nhập</a></p>
      </div>
    </section>
    <?php
    $content_page = ob_get_clean();
}

// LOGOUT
elseif ($r === 'logout') {
    session_destroy();
    header('Location: ?r=login');
    exit;
}

// KHU VUC YEU CAU DANG NHAP (AUTHENTICATED)
else {
    $u = login_required();
    // Chạy các lịch đến hạn ngay trong request ứng dụng, không yêu cầu người dùng
    // tự gọi PowerShell khi đang sử dụng WebShelf.
    run_due_schedules($pdo, $config);
    $unread_stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
    $unread_stmt->execute([(int) $u['id']]);
    $unread_notifications = (int) $unread_stmt->fetchColumn();

    // GET ASSET
    if ($r === 'asset') {
        $id = (int)($_GET['id'] ?? 0);
        $s = $pdo->prepare('SELECT a.*,p.library_id FROM assets a JOIN snapshots x ON x.id=a.snapshot_id JOIN saved_pages p ON p.id=x.saved_page_id WHERE a.id=?');
        $s->execute([$id]);
        $a = $s->fetch();
        if (!$a) exit;
        allow($pdo, (int)$a['library_id'], ['owner', 'editor', 'viewer']);
        $path = __DIR__ . '/uploads/' . $a['local_name'];
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $a['mime']);
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    // THONG BAO
    elseif ($r === 'notifications') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?')->execute([(int) $u['id']]);
            header('Location: ?r=notifications');
            exit;
        }
        $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50');
        $stmt->execute([(int) $u['id']]);
        $notifications = $stmt->fetchAll();
        $title_page = 'Thông báo';
        ob_start();
        ?>
        <section class="shell py-12">
          <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
            <div><p class="eyebrow mb-2">HỆ THỐNG</p><h1 class="text-3xl font-semibold">Thông báo</h1></div>
            <?php if ($unread_notifications > 0): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <button class="btn btn-light" type="submit">Đánh dấu đã đọc</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="space-y-3">
            <?php foreach ($notifications as $notification): ?>
              <article class="surface p-5 <?= (int) $notification['is_read'] === 0 ? 'border-[#17565a]' : '' ?>">
                <div class="flex items-start gap-3">
                  <span class="text-xl"><?= $notification['type'] === 'success' ? '✓' : '!' ?></span>
                  <div><h2 class="font-bold"><?= h($notification['title']) ?></h2>
                    <p class="text-sm text-[#607579] mt-1"><?= h($notification['message']) ?></p>
                    <time class="text-xs text-[#8a9b97] block mt-2"><?= h($notification['created_at']) ?></time>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><p class="surface p-6 text-[#607579]">Chưa có thông báo.</p><?php endif; ?>
          </div>
        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // TRANG CHU (HOME) - PHÂN TRANG BẤM SỐ & LUÔN HIỂN THỊ ĐỦ NÚT TIẾN/LÙI
    elseif ($r === 'home') {
        $title_page = 'Trang chủ';
        $s = $pdo->prepare('SELECT l.*,m.role FROM libraries l JOIN library_members m ON m.library_id=l.id WHERE m.user_id=?');
        $s->execute([$u['id']]);
        $libs = $s->fetchAll();

        // --- Cấu hình Phân trang (6 bài viết / trang) ---
        $limit = 6;
        $p_num = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        
        // Count tổng số bài viết của người dùng (dùng LEFT JOIN để hiển thị ngay cả khi chưa có snapshot)
        $count_stmt = $pdo->prepare('SELECT COUNT(DISTINCT p.id) FROM saved_pages p JOIN library_members m ON m.library_id=p.library_id LEFT JOIN snapshots x ON x.saved_page_id=p.id WHERE m.user_id = :user_id');
        $count_stmt->bindValue(':user_id', $u['id'], PDO::PARAM_INT);
        $count_stmt->execute();
        $total_items = (int)$count_stmt->fetchColumn();
        
        $total_pages = ceil($total_items / $limit);
        if ($p_num > $total_pages && $total_pages > 0) $p_num = $total_pages;
        $offset = ($p_num - 1) * $limit;

        // Query lấy bài viết cho trang hiện tại
        $recent_stmt = $pdo->prepare('SELECT p.id, p.title, p.source_url, l.name as library_name, COALESCE(MAX(x.fetched_at), "Đang xử lý...") as created_at 
            FROM saved_pages p 
            JOIN library_members m ON m.library_id=p.library_id 
            JOIN libraries l ON l.id=p.library_id 
            LEFT JOIN snapshots x ON x.saved_page_id=p.id 
            WHERE m.user_id = :user_id 
            GROUP BY p.id 
            ORDER BY p.id DESC 
            LIMIT :limit OFFSET :offset');
            
        $recent_stmt->bindValue(':user_id', $u['id'], PDO::PARAM_INT);
        $recent_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $recent_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $recent_stmt->execute();
        $recent_pages = $recent_stmt->fetchAll();

        ob_start();
        ?>
        <section class="shell mt-5">
          <div class="hero rounded-[18px] min-h-[410px] flex items-center">
            <img loading="lazy" src="https://images.pexels.com/photos/1370295/pexels-photo-1370295.jpeg?auto=compress&cs=tinysrgb&w=1920" alt="Thư viện">
            <div class="hero-content w-full max-w-[760px] p-7 sm:p-12 lg:p-16 text-white">
              <p class="text-[#f1c77a] uppercase tracking-[.2em] text-xs font-bold mb-5">THƯ VIỆN SỐ CỦA RIÊNG BẠN</p>
              <h1 class="text-[clamp(36px,5vw,64px)] leading-[1.09] mb-5 font-semibold">Lưu lại tri thức.<br>Đọc bất cứ lúc nào.</h1>
              <p class="text-[#e1ebea] max-w-[570px] leading-relaxed mb-7">Một không gian ngăn nắp để khám phá trải nghiệm lưu trang, sắp xếp thư viện và đọc lại những điều đáng nhớ.</p>
              
              <form action="" method="get" class="flex flex-col sm:flex-row gap-2 max-w-[620px] mb-5">
                <input type="hidden" name="r" value="search">
                <input name="q" class="field flex-1 text-gray-900" placeholder="Tìm kiếm trang đã lưu..." type="search" required>
                <button class="btn btn-gold" type="submit">Tìm kiếm</button>
              </form>

              <div class="flex flex-wrap gap-3">
                <a href="?r=save" class="btn btn-light">Lưu trang web <i data-lucide="arrow-up-right" class="w-4 h-4"></i></a>
                <a href="?r=library" class="btn text-white border border-white/50">Khám phá thư viện</a>
              </div>
            </div>
          </div>
        </section>

        <section class="shell py-14">
          <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
              <p class="eyebrow mb-2">KHÁM PHÁ</p>
              <h2 class="text-[30px] font-semibold text-[#123d42]">Trang đã lưu gần đây</h2>
            </div>
            <a href="?r=library" class="font-bold text-[#17565a] underline underline-offset-4">Xem tất cả thư viện</a>
          </div>

          <div class="grid md:grid-cols-3 gap-4">
            <?php if (empty($recent_pages)): ?>
              <p class="text-[#607579] col-span-3 text-center py-8 surface">Chưa có trang web nào được lưu.</p>
            <?php else: foreach ($recent_pages as $art): 
                $host = parse_url($art['source_url'], PHP_URL_HOST) ?: $art['source_url'];
                $label = trim((string)$art['title']) ?: $host;
            ?>
              <article class="surface p-5 flex flex-col justify-between gap-4">
                <div>
                  <p class="eyebrow mb-2"><?= h($art['library_name']) ?></p>
                  <h3 class="text-[21px] leading-snug mb-2 font-semibold"><?= h($label) ?></h3>
                </div>
                <div>
                  <div class="flex items-center justify-between gap-3 text-xs text-[#607579] mt-3">
                    <span><?= h($host) ?> · <?= h($art['created_at'] ?? 'Đang xử lý') ?></span>
                    <a href="?r=page&id=<?= $art['id'] ?>" class="font-bold text-[#17565a] underline underline-offset-4">Chi tiết</a>
                  </div>
                </div>
              </article>
            <?php endforeach; endif; ?>
          </div>

          <!-- THANH PHÂN TRANG BẤM SỐ (LUÔN HIỂN THỊ ĐỦ NÚT TIẾN/LÙI) -->
          <?php if ($total_pages > 1): ?>
            <div class="pagination-container mt-10">
              <ul class="pagination">
                
                <!-- Nút Về trang 1 & Lùi 1 trang -->
                <?php if ($p_num > 1): ?>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => 1])) ?>">&laquo;&laquo;</a></li>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $p_num - 1])) ?>">&laquo;</a></li>
                <?php else: ?>
                  <li class="page-item disabled"><span class="page-link">&laquo;&laquo;</span></li>
                  <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                <?php endif; ?>

                <!-- Danh sách các số trang -->
                <?php
                $start = max(1, $p_num - 2);
                $end   = min($total_pages, $p_num + 2);

                if ($start > 1) {
                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }

                for ($i = $start; $i <= $end; $i++): 
                ?>
                  <li class="page-item <?= ($i === $p_num) ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                  <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>

                <!-- Nút Tiến 1 trang & Đến trang cuối -->
                <?php if ($p_num < $total_pages): ?>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $p_num + 1])) ?>">&raquo;</a></li>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $total_pages])) ?>">&raquo;&raquo;</a></li>
                <?php else: ?>
                  <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                  <li class="page-item disabled"><span class="page-link">&raquo;&raquo;</span></li>
                <?php endif; ?>

              </ul>
            </div>
          <?php endif; ?>

        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // LUU TRANG (SAVE)
    elseif ($r === 'save') {
        $title_page = 'Lưu URL';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $lid = (int)($_POST['library_id'] ?? 0);
            allow($pdo, $lid, ['owner', 'editor']);
            try {
                $pdo->beginTransaction();
                $title = trim((string)($_POST['title'] ?? ''));
                if ($title === '' || mb_strlen($title) > 500) throw new RuntimeException('Tiêu đề phải có từ 1 đến 500 ký tự.');
                
                $url = trim((string)($_POST['url'] ?? ''));
                if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                    $url = "https://" . $url;
                }
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new RuntimeException('Đường dẫn URL không hợp lệ.');
                }

                $q = $pdo->prepare('INSERT INTO saved_pages(library_id,source_url,title,created_by) VALUES(?,?,?,?)');
                $q->execute([$lid, $url, $title, $u['id']]);
                $pid = (int)$pdo->lastInsertId();
                store_snapshot($pdo, $config, $pid, $url);
                replace_page_tags($pdo, $lid, $pid, (string)($_POST['tags'] ?? ''));
                $pdo->commit();
                header('Location: ?r=page&id=' . $pid);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
        $s = $pdo->prepare("SELECT l.id,l.name FROM libraries l JOIN library_members m ON m.library_id=l.id WHERE m.user_id=? AND m.role IN ('owner','editor') ORDER BY l.name");
        $s->execute([$u['id']]);
        $libraries = $s->fetchAll();

        ob_start();
        ?>
        <section class="shell py-12">
          <p class="eyebrow mb-2">THU THẬP</p>
          <h1 class="text-[clamp(32px,4vw,48px)] mb-3 font-semibold">Lưu trang web</h1>
          <p class="text-[#607579] max-w-2xl mb-7">Lưu bản ghi snapshot của trang web vào thư viện của bạn.</p>

          <?php if (isset($error)): ?>
            <p class="p-4 mb-4 rounded bg-red-100 text-red-700 text-sm font-semibold"><?= h($error) ?></p>
          <?php endif; ?>

          <?php if (empty($libraries)): ?>
            <p class="surface p-6 text-red-600 font-semibold">Bạn chưa có thư viện nào có quyền chỉnh sửa. Hãy tạo thư viện trước.</p>
          <?php else: ?>
            <div class="grid lg:grid-cols-[1.5fr_1fr] gap-6 items-start">
              <form method="post" class="surface p-6 sm:p-8 space-y-5">
                <input type="hidden" name="csrf" value="<?= csrf() ?>">
                <div>
                  <label class="block font-bold mb-2">Tiêu đề</label>
                  <input name="title" required maxlength="500" class="field" placeholder="Ví dụ: London - Wikipedia">
                </div>
                <div>
                  <label class="block font-bold mb-2">Thư viện đích</label>
                  <select name="library_id" class="field" required>
                    <?php foreach ($libraries as $lib): ?>
                      <option value="<?= (int)$lib['id'] ?>"><?= h($lib['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="block font-bold mb-2">URL trang web</label>
                  <input type="url" name="url" class="field" required placeholder="https://example.com/bai-viet">
                </div>
                <div>
                  <label class="block font-bold mb-2">Nhãn (phân cách bằng dấu phẩy)</label>
                  <input name="tags" maxlength="500" class="field" placeholder="PHP, Tin tức">
                </div>
                <button type="submit" class="btn btn-primary w-full sm:w-auto">Lấy và lưu snapshot</button>
              </form>

              <aside class="surface p-6">
                <span class="eyebrow">HƯỚNG DẪN</span>
                <h2 class="text-[23px] mt-3 mb-3 font-semibold">Quy trình xử lý</h2>
                <ol class="list-decimal pl-5 space-y-3 text-sm leading-relaxed text-[#567074]">
                  <li>Hệ thống kiểm tra tính hợp lệ và độ an toàn của URL.</li>
                  <li>Tải nội dung HTML và bóc tách dữ liệu bài viết.</li>
                  <li>Lưu bản ghi snapshot và hình ảnh để đọc lại offline.</li>
                </ol>
              </aside>
            </div>
          <?php endif; ?>
        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // TIK KIEM (SEARCH)
    elseif ($r === 'search') {
        $title_page = 'Tìm kiếm';
        $term = trim((string)($_GET['q'] ?? ''));
        $source = trim((string)($_GET['source'] ?? ''));
        $tag = trim((string)($_GET['tag'] ?? ''));

        ob_start();
        ?>
        <section class="shell py-12">
          <p class="eyebrow mb-2">KHÁM PHÁ</p>
          <h1 class="text-[clamp(32px,4vw,48px)] mb-3 font-semibold">Tìm kiếm nội dung</h1>
          <p class="text-[#607579] mb-6">Tìm trong tiêu đề, nội dung và tag của các bài viết đã lưu.</p>

          <form action="" method="get" class="surface p-5 mb-6">
            <input type="hidden" name="r" value="search">
            <div class="grid md:grid-cols-[1fr_auto] gap-3">
              <input name="q" value="<?= h($term) ?>" class="field" placeholder="Nhập từ khóa tìm kiếm..." autofocus>
              <button class="btn btn-primary" type="submit">Tìm kiếm</button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3 mt-4">
              <div>
                <label class="block text-xs font-bold mb-1">Nguồn (URL)</label>
                <input name="source" value="<?= h($source) ?>" class="field" placeholder="Lọc theo URL">
              </div>
              <div>
                <label class="block text-xs font-bold mb-1">Nhãn / Tag</label>
                <input name="tag" value="<?= h($tag) ?>" class="field" placeholder="Ví dụ: PHP">
              </div>
            </div>
          </form>

          <?php if ($term !== '' || $source !== '' || $tag !== ''): 
              $s = $pdo->prepare("SELECT DISTINCT x.id,x.title,x.plain_text,x.version_no,p.source_url FROM snapshots x JOIN saved_pages p ON p.id=x.saved_page_id JOIN library_members m ON m.library_id=p.library_id WHERE m.user_id=? AND (x.title LIKE ? OR x.plain_text LIKE ?) AND p.source_url LIKE ? AND (?='' OR EXISTS(SELECT 1 FROM saved_page_tags spt JOIN tags t ON t.id=spt.tag_id WHERE spt.saved_page_id=p.id AND t.name=?)) ORDER BY x.fetched_at DESC LIMIT 50");
              $like = '%' . $term . '%';
              $s->execute([$u['id'], $like, $like, '%' . $source . '%', $tag, $tag]);
              $results = $s->fetchAll();
          ?>
            <h2 class="text-[23px] mb-4 font-semibold"><?= count($results) ?> kết quả phù hợp</h2>
            <div class="grid gap-3">
              <?php foreach ($results as $x): 
                  $at = $term === '' ? false : mb_stripos($x['plain_text'], $term);
                  $snippet = $at === false && $term !== '' ? $x['title'] . ' — ' . $x['plain_text'] : mb_substr($x['plain_text'], max(0, ($at === false ? 0 : $at) - 70), 220);
              ?>
                <article class="surface p-5">
                  <a href="?r=read&id=<?= $x['id'] ?>">
                    <h3 class="text-xl font-bold text-[#17565a] hover:underline"><?= h($x['title']) ?> (v<?= $x['version_no'] ?>)</h3>
                  </a>
                  <p class="text-sm leading-relaxed text-[#567074] my-2"><?= h($snippet) ?>…</p>
                  <small class="text-xs text-[#607579]"><?= h($x['source_url']) ?></small>
                </article>
              <?php endforeach; ?>
              <?php if (empty($results)): ?>
                <p class="surface p-6 text-center text-[#607579]">Không tìm thấy kết quả nào.</p>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <p class="surface p-6 text-center text-[#607579]">Nhập từ khóa để tìm kiếm trong kho lưu trữ.</p>
          <?php endif; ?>
        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // DOC PHIEN BAN (READ)
    elseif ($r === 'read') {
        $x = snapshot($pdo, (int)$_GET['id']);
        allow($pdo, (int)$x['library_id'], ['owner', 'editor', 'viewer']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $body = trim((string)($_POST['body'] ?? ''));
            if ($body !== '') {
                $s = $pdo->prepare('INSERT INTO notes(snapshot_id,user_id,body) VALUES(?,?,?)');
                $s->execute([$x['id'], $u['id'], $body]);
            }
            header('Location: ?r=read&id=' . $x['id']);
            exit;
        }
        $s = $pdo->prepare('SELECT n.*,u.name FROM notes n JOIN users u ON u.id=n.user_id WHERE snapshot_id=? ORDER BY n.created_at');
        $s->execute([$x['id']]);
        $notes = $s->fetchAll();
        $title_page = $x['title'];

        ob_start();
        ?>
        <section class="shell py-10">
          <a href="?r=page&id=<?= $x['saved_page_id'] ?>" class="font-bold text-[#17565a] inline-flex items-center gap-2 mb-7">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Quay lại danh sách phiên bản
          </a>
          <div class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-7 items-start">
            <article class="surface p-6 sm:p-10">
              <p class="eyebrow mb-3">PHIÊN BẢN v<?= $x['version_no'] ?> · <?= h($x['fetched_at']) ?></p>
              <h1 class="text-[clamp(32px,4vw,49px)] leading-tight font-semibold"><?= h($x['title']) ?></h1>
              <div class="border-t border-[#dae4df] pt-7 mt-6 article-body">
                <?= $x['clean_html'] ?>
              </div>
            </article>

            <aside class="space-y-4">
              <div class="surface p-5">
                <h2 class="text-[22px] font-semibold mb-3">Ghi chú</h2>
                <div class="space-y-3 mb-4">
                  <?php foreach ($notes as $n): ?>
                    <div class="border-b pb-2 text-sm">
                      <strong><?= h($n['name']) ?>:</strong> <?= nl2br(h($n['body'])) ?>
                    </div>
                  <?php endforeach; ?>
                  <?php if (empty($notes)): ?>
                    <p class="text-xs text-[#607579]">Chưa có ghi chú nào.</p>
                  <?php endif; ?>
                </div>

                <form method="post" class="space-y-2">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <textarea name="body" required maxlength="4000" class="field text-sm" rows="3" placeholder="Viết ghi chú..."></textarea>
                  <button class="btn btn-primary text-xs w-full">Thêm ghi chú</button>
                </form>
              </div>
            </aside>
          </div>
        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // QUAN LY THU VIEN (LIBRARY)
    elseif ($r === 'library') {
        $title_page = 'Quản lý thư viện';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') $error = 'Tên thư viện là bắt buộc.';
            else {
                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO libraries(name,owner_id) VALUES(?,?)')->execute([$name, $u['id']]);
                $lid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO library_members VALUES(?,?,'owner')")->execute([$lid, $u['id']]);
                $pdo->commit();
                header('Location: ?r=library');
                exit;
            }
        }

        // --- Cấu hình phân trang cho danh sách thư viện (Tối đa 6 thư viện / trang) ---
        $limit = 6;
        $p_num = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

        // Đếm tổng số thư viện của người dùng
        $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM libraries WHERE owner_id = ?');
        $count_stmt->execute([$u['id']]);
        $total_items = (int)$count_stmt->fetchColumn();

        $total_pages = ceil($total_items / $limit);
        if ($p_num > $total_pages && $total_pages > 0) $p_num = $total_pages;
        $offset = ($p_num - 1) * $limit;

        // Lấy danh sách thư viện theo trang
        $s = $pdo->prepare('SELECT l.id, l.name, l.created_at FROM libraries l WHERE l.owner_id = :owner_id ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset');
        $s->bindValue(':owner_id', $u['id'], PDO::PARAM_INT);
        $s->bindValue(':limit', $limit, PDO::PARAM_INT);
        $s->bindValue(':offset', $offset, PDO::PARAM_INT);
        $s->execute();
        $my_libraries = $s->fetchAll();

        ob_start();
        ?>
        <section class="shell py-12">
          <p class="eyebrow mb-2">BỘ SƯU TẬP</p>
          <h1 class="text-[clamp(32px,4vw,48px)] font-semibold mb-6">Thư viện của tôi</h1>

          <div class="surface p-6 mb-8 max-w-xl">
            <h2 class="text-xl font-bold mb-4">Tạo thư viện mới</h2>
            <?php if (isset($error)): ?>
              <p class="p-2 mb-3 bg-red-100 text-red-700 text-xs font-semibold rounded"><?= h($error) ?></p>
            <?php endif; ?>
            <form method="post" class="flex gap-2">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input name="name" required maxlength="120" class="field" placeholder="Ví dụ: Thiết kế & Ý tưởng">
              <button type="submit" class="btn btn-primary shrink-0">Tạo ngay</button>
            </form>
          </div>

          <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($my_libraries as $lib): ?>
              <article class="surface p-5 flex flex-col justify-between">
                <div>
                  <span class="eyebrow">OWNER</span>
                  <h2 class="text-[23px] font-semibold my-2"><?= h($lib['name']) ?></h2>
                  <p class="text-xs text-[#607579] mb-4">Tạo ngày <?= h($lib['created_at']) ?></p>
                </div>
                <div class="flex flex-wrap gap-2 pt-3 border-t">
                  <a href="?r=library-detail&id=<?= (int)$lib['id'] ?>" class="btn btn-light !py-2 text-sm">Xem chi tiết</a>
                  <a href="?r=members&library_id=<?= (int)$lib['id'] ?>" class="btn btn-light !py-2 text-sm">Thành viên</a>
                  <form method="post" action="?r=delete-library" onsubmit="return confirm('Xóa thư viện này?');" class="inline">
                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                    <input type="hidden" name="library_id" value="<?= (int)$lib['id'] ?>">
                    <button type="submit" class="btn btn-light !py-2 text-sm text-red-600">Xóa</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
            <?php if (empty($my_libraries)): ?>
              <p class="surface p-6 text-center text-[#607579] col-span-3">Bạn chưa tạo thư viện nào.</p>
            <?php endif; ?>
          </div>

          <!-- THANH PHÂN TRANG BẤM SỐ -->
          <?php if ($total_pages > 1): ?>
            <div class="pagination-container mt-10">
              <ul class="pagination">
                
                <!-- Nút Về trang 1 & Lùi 1 trang -->
                <?php if ($p_num > 1): ?>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => 1])) ?>">&laquo;&laquo;</a></li>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $p_num - 1])) ?>">&laquo;</a></li>
                <?php else: ?>
                  <li class="page-item disabled"><span class="page-link">&laquo;&laquo;</span></li>
                  <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                <?php endif; ?>

                <!-- Danh sách các số trang -->
                <?php
                $start = max(1, $p_num - 2);
                $end   = min($total_pages, $p_num + 2);

                if ($start > 1) {
                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }

                for ($i = $start; $i <= $end; $i++): 
                ?>
                  <li class="page-item <?= ($i === $p_num) ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                  <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>

                <!-- Nút Tiến 1 trang & Đến trang cuối -->
                <?php if ($p_num < $total_pages): ?>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $p_num + 1])) ?>">&raquo;</a></li>
                  <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['p' => $total_pages])) ?>">&raquo;&raquo;</a></li>
                <?php else: ?>
                  <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                  <li class="page-item disabled"><span class="page-link">&raquo;&raquo;</span></li>
                <?php endif; ?>

              </ul>
            </div>
          <?php endif; ?>

        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // CHI TIET TRANG DANG LUU (PAGE)
    // CHI TIET TRANG DANG LUU (PAGE) - HIỂN THỊ DANH SÁCH & NÚT XÓA TỪNG SNAPSHOT
    elseif ($r === 'page') {
        $p = page_library($pdo, (int)$_GET['id']);
        allow($pdo, (int)$p['library_id'], ['owner', 'editor', 'viewer']);
        $schedule_stmt = $pdo->prepare('SELECT * FROM update_schedules WHERE saved_page_id=?');
        $schedule_stmt->execute([(int) $p['id']]);
        $schedule = $schedule_stmt->fetch();
        $can_schedule = in_array(role($pdo, (int) $p['library_id'], (int) $u['id']), ['owner', 'editor'], true);
        $s = $pdo->prepare('SELECT id,version_no,title,fetched_at FROM snapshots WHERE saved_page_id=? ORDER BY version_no DESC');
        $s->execute([$p['id']]);
        $versions = $s->fetchAll();
        $title_page = $p['title'];

        ob_start();
        ?>
        <section class="shell py-12">
          <a href="?r=library-detail&id=<?= $p['library_id'] ?>" class="font-bold text-[#17565a] inline-flex items-center gap-2 mb-6">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Quay lại thư viện
          </a>
          <div class="surface p-6 sm:p-8">
            <h1 class="text-3xl font-semibold mb-2"><?= h($p['title']) ?></h1>
            <p class="text-sm text-[#607579] mb-6">Nguồn: <a href="<?= h($p['source_url']) ?>" target="_blank" class="underline"><?= h($p['source_url']) ?></a></p>

            <?php if ($can_schedule): ?>
              <div class="border border-[#dae4df] rounded-xl p-5 mb-7 bg-[#f8fbfa]">
                <div class="flex flex-wrap justify-between gap-3 items-start">
                  <div><h2 class="font-bold text-lg">Tự động cập nhật version</h2>
                    <p class="text-sm text-[#607579] mt-1">Worker sẽ lấy bản mới khi lịch đến hạn và tạo snapshot tiếp theo.</p></div>
                  <?php if ($schedule): ?>
                    <form method="post" action="?r=schedule-delete" onsubmit="return confirm('Tắt lịch cập nhật cho trang này?');">
                      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="page_id" value="<?= (int) $p['id'] ?>">
                      <button class="btn btn-light text-red-600" type="submit">Tắt lịch</button>
                    </form>
                  <?php endif; ?>
                </div>
                <form method="post" action="?r=schedule-save" class="grid md:grid-cols-2 gap-4 mt-4">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="page_id" value="<?= (int) $p['id'] ?>">
                  <label class="text-sm font-bold">Chu kỳ
                    <select name="frequency" class="field mt-1">
                      <?php foreach (['hourly'=>'Mỗi giờ','daily'=>'Mỗi ngày','weekly'=>'Mỗi tuần','monthly'=>'Mỗi tháng'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($schedule['frequency'] ?? 'daily') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label class="text-sm font-bold">Giờ chạy
                    <input type="time" name="run_time" class="field mt-1" value="<?= h(substr((string)($schedule['run_time'] ?? '08:00'), 0, 5)) ?>" required>
                  </label>
                  <label class="text-sm font-bold">Ngày trong tuần (lịch tuần)
                    <select name="day_of_week" class="field mt-1">
                      <?php foreach (['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'] as $day => $label): ?>
                        <option value="<?= $day ?>" <?= ((int)($schedule['day_of_week'] ?? 1) === $day) ? 'selected' : '' ?>><?= $label ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label class="text-sm font-bold">Ngày trong tháng (lịch tháng)
                    <input type="number" name="day_of_month" class="field mt-1" min="1" max="31" value="<?= (int)($schedule['day_of_month'] ?? 1) ?>">
                  </label>
                  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify_success" value="1" <?= (!$schedule || (int)$schedule['notify_success'] === 1) ? 'checked' : '' ?>> Thông báo khi thành công</label>
                  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="notify_failure" value="1" <?= (!$schedule || (int)$schedule['notify_failure'] === 1) ? 'checked' : '' ?>> Thông báo khi lỗi</label>
                  <div class="md:col-span-2"><button class="btn btn-primary" type="submit"><?= $schedule ? 'Lưu thay đổi lịch' : 'Bật lịch cập nhật' ?></button>
                    <?php if ($schedule): ?><span class="text-sm text-[#607579] ml-3"><?= $schedule['last_status'] === 'failed' ? 'Lần gần nhất lỗi: ' . h((string)$schedule['last_error']) : 'Lần chạy kế tiếp: ' . h((string)$schedule['next_run_at']) ?></span><?php endif; ?>
                  </div>
                </form>
              </div>
            <?php endif; ?>

            <h2 class="text-xl font-bold mb-4">Các phiên bản snapshot (<?= count($versions) ?>)</h2>
            <div class="space-y-3">
              <?php foreach ($versions as $x): ?>
                <div class="p-4 border rounded-lg flex flex-wrap justify-between items-center bg-gray-50 gap-3">
                  <div>
                    <a href="?r=read&id=<?= $x['id'] ?>" class="font-bold text-[#17565a] hover:underline text-lg">
                      v<?= $x['version_no'] ?> — <?= h($x['title']) ?>
                    </a>
                    <span class="text-xs text-[#607579] block mt-1">Thời điểm lưu: <?= h($x['fetched_at']) ?></span>
                  </div>

                  <div class="flex items-center gap-2">
                    <a href="?r=read&id=<?= $x['id'] ?>" class="btn btn-light text-xs !py-1.5">Đọc phiên bản này</a>
                    
                    <!-- Nút Xóa Từng Phiên Bản Snapshot -->
                    <form method="post" action="?r=delete-snapshot" onsubmit="return confirm('Xóa phiên bản snapshot v<?= $x['version_no'] ?> này?');" class="inline">
                      <input type="hidden" name="csrf" value="<?= csrf() ?>">
                      <input type="hidden" name="snapshot_id" value="<?= (int)$x['id'] ?>">
                      <input type="hidden" name="page_id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="btn btn-light text-xs !py-1.5 text-red-600 hover:bg-red-50 border-red-200" title="Xóa phiên bản này">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5 inline"></i> Xóa
                      </button>
                    </form>
                  </div>
                </div>
              <?php endforeach; ?>

              <?php if (empty($versions)): ?>
                <p class="text-center py-6 text-[#607579]">Trang này chưa có phiên bản snapshot nào.</p>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <?php
        $content_page = ob_get_clean();
    }

    elseif ($r === 'schedule-save' || $r === 'schedule-delete') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Phương thức không được hỗ trợ.'); }
        verify_csrf();
        $pageId = (int) ($_POST['page_id'] ?? 0);
        $page = page_library($pdo, $pageId);
        allow($pdo, (int) $page['library_id'], ['owner', 'editor']);
        if ($r === 'schedule-delete') {
            $pdo->prepare('DELETE FROM update_schedules WHERE saved_page_id=?')->execute([$pageId]);
        } else {
            $frequency = (string) ($_POST['frequency'] ?? 'daily');
            if (!in_array($frequency, ['hourly', 'daily', 'weekly', 'monthly'], true)) { http_response_code(422); exit('Chu kỳ không hợp lệ.'); }
            $runTime = (string) ($_POST['run_time'] ?? '');
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $runTime)) { http_response_code(422); exit('Giờ chạy không hợp lệ.'); }
            $scheduleData = [
                'frequency' => $frequency, 'run_time' => $runTime . ':00',
                'day_of_week' => max(0, min(6, (int)($_POST['day_of_week'] ?? 1))),
                'day_of_month' => max(1, min(31, (int)($_POST['day_of_month'] ?? 1))),
            ];
            $nextRun = schedule_next_run($scheduleData, new DateTimeImmutable('now'));
            $stmt = $pdo->prepare(
                'INSERT INTO update_schedules(saved_page_id,created_by,frequency,run_time,day_of_week,day_of_month,notify_success,notify_failure,next_run_at)
                 VALUES(?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE frequency=VALUES(frequency),run_time=VALUES(run_time),day_of_week=VALUES(day_of_week),day_of_month=VALUES(day_of_month),notify_success=VALUES(notify_success),notify_failure=VALUES(notify_failure),enabled=1,next_run_at=VALUES(next_run_at)'
            );
            $stmt->execute([$pageId, (int)$u['id'], $frequency, $scheduleData['run_time'], $scheduleData['day_of_week'], $scheduleData['day_of_month'], isset($_POST['notify_success']) ? 1 : 0, isset($_POST['notify_failure']) ? 1 : 0, $nextRun->format('Y-m-d H:i:s')]);
        }
        header('Location: ?r=page&id=' . $pageId);
        exit;
    }

    // QUAN LY THANH VIEN THU VIEN
    elseif ($r === 'members') {
        $libraryId = (int) ($_GET['library_id'] ?? $_POST['library_id'] ?? 0);
        $libraryStmt = $pdo->prepare(
            'SELECT l.id,l.name,m.role FROM libraries l
             JOIN library_members m ON m.library_id=l.id
             WHERE l.id=? AND m.user_id=?'
        );
        $libraryStmt->execute([$libraryId, (int) $u['id']]);
        $library = $libraryStmt->fetch();
        if (!$library) {
            http_response_code(404);
            exit('Không tìm thấy thư viện.');
        }
        if ($library['role'] !== 'owner') {
            http_response_code(403);
            exit('Chỉ chủ thư viện mới có thể quản lý thành viên.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $action = (string) ($_POST['action'] ?? '');
            $memberId = (int) ($_POST['user_id'] ?? 0);
            try {
                if ($action === 'add') {
                    $email = trim((string) ($_POST['email'] ?? ''));
                    $memberRole = (string) ($_POST['role'] ?? 'viewer');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('Email thành viên không hợp lệ.');
                    }
                    if (!in_array($memberRole, ['editor', 'viewer'], true)) {
                        throw new RuntimeException('Vai trò không hợp lệ.');
                    }
                    $userStmt = $pdo->prepare('SELECT id FROM users WHERE email=?');
                    $userStmt->execute([$email]);
                    $member = $userStmt->fetch();
                    if (!$member) {
                        throw new RuntimeException('Không tìm thấy tài khoản với email này.');
                    }
                    $pdo->prepare(
                        'INSERT INTO library_members(library_id,user_id,role) VALUES(?,?,?)
                         ON DUPLICATE KEY UPDATE role=VALUES(role)'
                    )->execute([$libraryId, (int) $member['id'], $memberRole]);
                } elseif ($action === 'role') {
                    $memberRole = (string) ($_POST['role'] ?? '');
                    if ($memberId === (int) $u['id'] || !in_array($memberRole, ['editor', 'viewer'], true)) {
                        throw new RuntimeException('Không thể thay đổi vai trò owner.');
                    }
                    $pdo->prepare('UPDATE library_members SET role=? WHERE library_id=? AND user_id=? AND role<>?')
                        ->execute([$memberRole, $libraryId, $memberId, 'owner']);
                } elseif ($action === 'remove') {
                    if ($memberId === (int) $u['id']) {
                        throw new RuntimeException('Không thể xóa owner khỏi thư viện.');
                    }
                    $pdo->prepare('DELETE FROM library_members WHERE library_id=? AND user_id=? AND role<>?')
                        ->execute([$libraryId, $memberId, 'owner']);
                } else {
                    throw new RuntimeException('Thao tác thành viên không hợp lệ.');
                }
                header('Location: ?r=members&library_id=' . $libraryId);
                exit;
            } catch (RuntimeException $error) {
                $memberError = $error->getMessage();
            }
        }

        $membersStmt = $pdo->prepare(
            'SELECT u.id,u.name,u.email,m.role
             FROM library_members m JOIN users u ON u.id=m.user_id
             WHERE m.library_id=? ORDER BY FIELD(m.role,"owner","editor","viewer"),u.name'
        );
        $membersStmt->execute([$libraryId]);
        $members = $membersStmt->fetchAll();
        $title_page = 'Thành viên thư viện';
        ob_start();
        ?>
        <section class="shell py-12">
          <a href="?r=library" class="font-bold text-[#17565a] inline-flex items-center gap-2 mb-6">← Quay lại thư viện</a>
          <div class="flex flex-wrap justify-between items-end gap-4 mb-6">
            <div><p class="eyebrow mb-2">PHÂN QUYỀN</p><h1 class="text-3xl font-semibold"><?= h($library['name']) ?></h1></div>
          </div>
          <?php if (isset($memberError)): ?>
            <p class="p-4 mb-5 rounded bg-red-100 text-red-700 text-sm font-semibold"><?= h($memberError) ?></p>
          <?php endif; ?>
          <div class="surface p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Thêm thành viên</h2>
            <form method="post" class="grid md:grid-cols-[1fr_180px_auto] gap-3 items-end">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="library_id" value="<?= $libraryId ?>">
              <input type="hidden" name="action" value="add">
              <label class="text-sm font-bold">Email tài khoản
                <input type="email" name="email" class="field mt-1" required placeholder="user@example.com">
              </label>
              <label class="text-sm font-bold">Vai trò
                <select name="role" class="field mt-1"><option value="viewer">Viewer</option><option value="editor">Editor</option></select>
              </label>
              <button class="btn btn-primary" type="submit">Thêm thành viên</button>
            </form>
          </div>
          <div class="surface overflow-hidden">
            <div class="p-5 border-b"><h2 class="text-xl font-bold">Danh sách thành viên (<?= count($members) ?>)</h2></div>
            <div class="divide-y">
              <?php foreach ($members as $member): ?>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                  <div><p class="font-bold"><?= h($member['name']) ?></p><p class="text-sm text-[#607579]"><?= h($member['email']) ?></p></div>
                  <div class="flex items-center gap-2">
                    <?php if ($member['role'] === 'owner'): ?>
                      <span class="eyebrow">OWNER</span>
                    <?php else: ?>
                      <form method="post" class="flex items-center gap-2">
                        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="library_id" value="<?= $libraryId ?>">
                        <input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                        <select name="role" class="field !py-2 text-sm" onchange="this.form.submit()">
                          <option value="editor" <?= $member['role'] === 'editor' ? 'selected' : '' ?>>Editor</option>
                          <option value="viewer" <?= $member['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                      </form>
                      <form method="post" onsubmit="return confirm('Xóa thành viên này khỏi thư viện?');">
                        <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="library_id" value="<?= $libraryId ?>">
                        <input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= (int) $member['id'] ?>">
                        <button class="btn btn-light text-red-600 !py-2 text-sm" type="submit">Xóa</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php
        $content_page = ob_get_clean();
    }

    // ROUTE XÓA 1 PHIÊN BẢN SNAPSHOT (DELETE-SNAPSHOT)
    elseif ($r === 'delete-snapshot') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $snapshot_id = (int)($_POST['snapshot_id'] ?? 0);
            $page_id = (int)($_POST['page_id'] ?? 0);

            // 1. Kiểm tra thông tin snapshot và quyền truy cập thư viện
            $stmt = $pdo->prepare('SELECT x.id, x.saved_page_id, p.library_id FROM snapshots x JOIN saved_pages p ON p.id = x.saved_page_id WHERE x.id = ?');
            $stmt->execute([$snapshot_id]);
            $snap = $stmt->fetch();

            if ($snap) {
                // Kiểm tra quyền (chỉ Owner hoặc Editor trong thư viện mới được phép xóa)
                allow($pdo, (int)$snap['library_id'], ['owner', 'editor']);

                try {
                    $pdo->beginTransaction();

                    // 2. Xóa ghi chú (notes) của snapshot này
                    $pdo->prepare('DELETE FROM notes WHERE snapshot_id = ?')->execute([$snapshot_id]);

                    // 3. Xóa tài nguyên hình ảnh (assets) đính kèm của snapshot này
                    $pdo->prepare('DELETE FROM assets WHERE snapshot_id = ?')->execute([$snapshot_id]);

                    // 4. Xóa chính bản ghi snapshot này
                    $pdo->prepare('DELETE FROM snapshots WHERE id = ?')->execute([$snapshot_id]);

                    // 5. Kiểm tra xem trang đó còn phiên bản snapshot nào khác không
                    $checkSnap = $pdo->prepare('SELECT COUNT(*) FROM snapshots WHERE saved_page_id = ?');
                    $checkSnap->execute([$page_id]);
                    $remaining = (int)$checkSnap->fetchColumn();

                    // Nếu đây là snapshot cuối cùng của trang thì tiến hành xóa luôn bài viết
                    if ($remaining === 0) {
                        $pdo->prepare('DELETE FROM saved_page_tags WHERE saved_page_id = ?')->execute([$page_id]);
                        $pdo->prepare('DELETE FROM saved_pages WHERE id = ?')->execute([$page_id]);
                        $pdo->commit();
                        
                        // Chuyển hướng về danh sách bài viết trong thư viện
                        header('Location: ?r=library-detail&id=' . (int)$snap['library_id']);
                        exit;
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                }
            }
            
            // Nếu vẫn còn snapshot khác, quay lại trang xem danh sách snapshot của bài viết
            header('Location: ?r=page&id=' . $page_id);
            exit;
        }

        header('Location: ?r=home');
        exit;
    }

    // XOA THU VIEN (DELETE-LIBRARY)
    elseif ($r === 'delete-library') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $library_id = (int)($_POST['library_id'] ?? 0);

            // Kiểm tra quyền: Chỉ Owner mới có quyền xóa thư viện
            allow($pdo, $library_id, ['owner']);

            try {
                $pdo->beginTransaction();

                // 1. Lấy danh sách ID các trang đã lưu thuộc thư viện này
                $stmtPages = $pdo->prepare('SELECT id FROM saved_pages WHERE library_id = ?');
                $stmtPages->execute([$library_id]);
                $pageIds = $stmtPages->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($pageIds)) {
                    $inPages = implode(',', array_fill(0, count($pageIds), '?'));

                    // 2. Xóa các ghi chú (notes) liên quan đến snapshots của các trang trong thư viện
                    $stmtNotes = $pdo->prepare("DELETE FROM notes WHERE snapshot_id IN (SELECT id FROM snapshots WHERE saved_page_id IN ($inPages))");
                    $stmtNotes->execute($pageIds);

                    // 3. Xóa các liên kết thẻ (saved_page_tags)
                    $stmtTags = $pdo->prepare("DELETE FROM saved_page_tags WHERE saved_page_id IN ($inPages)");
                    $stmtTags->execute($pageIds);

                    // 4. Xóa các tài nguyên đính kèm (assets) & snapshots
                    $stmtAssets = $pdo->prepare("DELETE FROM assets WHERE snapshot_id IN (SELECT id FROM snapshots WHERE saved_page_id IN ($inPages))");
                    $stmtAssets->execute($pageIds);

                    $stmtSnapshots = $pdo->prepare("DELETE FROM snapshots WHERE saved_page_id IN ($inPages)");
                    $stmtSnapshots->execute($pageIds);

                    // 5. Xóa các trang đã lưu (saved_pages)
                    $stmtDeletePages = $pdo->prepare('DELETE FROM saved_pages WHERE library_id = ?');
                    $stmtDeletePages->execute([$library_id]);
                }

                // 6. Xóa các tag thuộc thư viện
                $pdo->prepare('DELETE FROM tags WHERE library_id = ?')->execute([$library_id]);

                // 7. Xóa thành viên thuộc thư viện (library_members)
                $pdo->prepare('DELETE FROM library_members WHERE library_id = ?')->execute([$library_id]);

                // 8. Cuối cùng: Xóa thư viện (libraries)
                $pdo->prepare('DELETE FROM libraries WHERE id = ?')->execute([$library_id]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        
        // Sau khi xóa xong, chuyển hướng về trang Quản lý thư viện
        header('Location: ?r=library');
        exit;
    }

    // MAC DINH: HO TRO CAC ROUTE PHU CO SAN (LIBRARY-DETAIL CÓ PHÂN TRANG)
    else {
        ob_start();
        if ($r === 'library-detail') {
            $libraryId = (int)($_GET['id'] ?? 0);
            $library = $pdo->prepare('SELECT l.id,l.name,m.role FROM libraries l JOIN library_members m ON m.library_id=l.id WHERE l.id=? AND m.user_id=?');
            $library->execute([$libraryId, $u['id']]);
            $library = $library->fetch();
            if (!$library) { http_response_code(404); exit('Không tìm thấy thư viện.'); }

            // --- Phân trang chi tiết thư viện ---
            $limit = 6;
            $p_num = isset($_GET['p']) && is_numeric($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

            $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM saved_pages WHERE library_id=?');
            $count_stmt->execute([$libraryId]);
            $total_items = (int)$count_stmt->fetchColumn();

            $total_pages = ceil($total_items / $limit);
            if ($p_num > $total_pages && $total_pages > 0) $p_num = $total_pages;
            $offset = ($p_num - 1) * $limit;

            $pagesQuery = $pdo->prepare('SELECT p.id, p.source_url, p.title, MAX(x.fetched_at) AS latest 
            FROM saved_pages p 
            LEFT JOIN snapshots x ON x.saved_page_id = p.id 
            WHERE p.library_id = :library_id 
            GROUP BY p.id 
            ORDER BY latest DESC, p.id DESC 
            LIMIT :limit OFFSET :offset');

            $pagesQuery->bindValue(':library_id', $libraryId, PDO::PARAM_INT);
            $pagesQuery->bindValue(':limit', $limit, PDO::PARAM_INT);
            $pagesQuery->bindValue(':offset', $offset, PDO::PARAM_INT);
            $pagesQuery->execute();
            $pages = $pagesQuery->fetchAll();

            echo '<div class="shell py-12"><h1 class="text-3xl font-bold mb-6">' . h($library['name']) . '</h1><div class="grid gap-3 mb-8">';
            if (empty($pages)) {
                echo '<p class="surface p-6 text-center text-[#607579]">Thư viện này chưa có bài viết nào.</p>';
            } else {
                foreach ($pages as $page) {
                    $label = trim((string)($page['title'] ?? '')) ?: (parse_url($page['source_url'], PHP_URL_HOST) ?: $page['source_url']);
                    echo '<a href="?r=page&id=' . (int)$page['id'] . '" class="surface p-4 block font-semibold hover:border-[#17565a]">' . h($label) . '</a>';
                }
            }
            echo '</div>';

            // Hiển thị phân trang số bấm (Đầy đủ nút Tiến / Lùi)
            if ($total_pages > 1) {
                echo '<div class="pagination-container mt-10"><ul class="pagination">';
                
                if ($p_num > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['p' => 1])) . '">&laquo;&laquo;</a></li>';
                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['p' => $p_num - 1])) . '">&laquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&laquo;&laquo;</span></li>';
                    echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
                }

                $start = max(1, $p_num - 2);
                $end   = min($total_pages, $p_num + 2);

                if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';

                for ($i = $start; $i <= $end; $i++) {
                    $active = ($i === $p_num) ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['p' => $i])) . '">' . $i . '</a></li>';
                }

                if ($end < $total_pages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';

                if ($p_num < $total_pages) {
                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['p' => $p_num + 1])) . '">&raquo;</a></li>';
                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['p' => $total_pages])) . '">&raquo;&raquo;</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
                    echo '<li class="page-item disabled"><span class="page-link">&raquo;&raquo;</span></li>';
                }
                echo '</ul></div>';
            }

            echo '</div>';
        } else {
            echo '<div class="shell py-12"><p>Đang tải trang hoặc trang không tồn tại.</p></div>';
        }
        $content_page = ob_get_clean();
        $title_page = 'WebShelf';
    }
}

// -----------------------------------------------------------------------------
// 2. RENDER GIAO DIEN HTML CHINH
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($title_page ?? 'WebShelf') ?> — Thư viện số của bạn</title>
  
  <script src="https://cdn.tailwindcss.com/3.4.17" type="text/javascript"></script>
  <script src="https://cdn.jsdelivr.net/npm/lucide@0.577.0/dist/umd/lucide.min.js" type="text/javascript"></script>
  <link rel="stylesheet" href="assets/styles.css?v=<?= time() ?>">
  <style>
    /* Style bổ sung cho các nút phân trang bấm số */
    .pagination-container {
        display: flex;
        justify-content: center;
    }
    .pagination {
        display: flex;
        list-style: none;
        padding: 0;
        margin: 0;
        gap: 6px;
    }
    .page-item .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 38px;
        height: 38px;
        padding: 0 12px;
        color: #17565a;
        background-color: #ffffff;
        border: 1px solid #dae4df;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.2s ease-in-out;
    }
    .page-item .page-link:hover {
        background-color: #f0f4f3;
        border-color: #17565a;
    }
    .page-item.active .page-link {
        color: #ffffff;
        background-color: #17565a;
        border-color: #17565a;
    }
    .page-item.disabled .page-link {
        color: #cbd5e1;
        pointer-events: none;
        background-color: #f8fafc;
        border-color: #e2e8f0;
        cursor: not-allowed;
    }
  </style>
</head>
<body class="w-full min-h-screen">

  <!-- Header -->
  <header class="canva-header bg-white border-b border-[#dae4df] sticky top-0 z-30">
    <div class="shell flex items-center justify-between gap-5 py-3">
      <a href="?r=home" class="flex items-center gap-3 text-left shrink-0">
        <span class="w-10 h-10 rounded-lg bg-[#17565a] text-white grid place-items-center">
          <i data-lucide="library-big" class="w-5 h-5"></i>
        </span>
        <span class="font-bold tracking-tight text-[21px]" style="color: rgb(18, 61, 66);">WebShelf</span>
      </a>

      <nav id="desktop-nav" class="flex items-center gap-7" aria-label="Điều hướng chính">
        <a class="nav-link <?= $r === 'home' ? 'active' : '' ?>" href="?r=home">Trang chủ</a>
        <a class="nav-link <?= $r === 'library' ? 'active' : '' ?>" href="?r=library">Thư viện của tôi</a>
        <a class="nav-link <?= $r === 'save' ? 'active' : '' ?>" href="?r=save">Lưu trang</a>
        <a class="nav-link <?= $r === 'search' ? 'active' : '' ?>" href="?r=search">Tìm kiếm</a>
        <?php if (isset($u) && $u): ?><a class="nav-link <?= $r === 'notifications' ? 'active' : '' ?>" href="?r=notifications">Thông báo<?= $unread_notifications > 0 ? ' (' . $unread_notifications . ')' : '' ?></a><?php endif; ?>
      </nav>

      <div class="flex items-center gap-2">
        <?php if (isset($u) && $u): ?>
          <span class="btn btn-light !py-2 !px-3 text-sm">
            <i data-lucide="user-round" class="w-4 h-4"></i>
            <span class="hidden sm:inline"><?= h($u['name']) ?></span>
          </span>
          <a href="?r=logout" class="btn btn-light !py-2 !px-3 text-sm text-red-600">Đăng xuất</a>
        <?php else: ?>
          <a href="?r=login" class="btn btn-light !py-2 !px-3 text-sm">Đăng nhập</a>
          <a href="?r=register" class="btn btn-primary !py-2 !px-3 text-sm">Đăng ký</a>
        <?php endif; ?>
        <button id="mobile-toggle" type="button" class="btn btn-light !p-2" aria-label="Mở menu">
          <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
      </div>
    </div>

    <nav id="mobile-nav" hidden class="shell pb-4 gap-1" aria-label="Điều hướng di động">
      <a href="?r=home" class="text-left py-2">Trang chủ</a>
      <a href="?r=library" class="text-left py-2">Thư viện của tôi</a>
      <a href="?r=save" class="text-left py-2">Lưu trang</a>
      <a href="?r=search" class="text-left py-2">Tìm kiếm</a>
      <?php if (isset($u) && $u): ?><a href="?r=notifications" class="text-left py-2">Thông báo<?= $unread_notifications > 0 ? ' (' . $unread_notifications . ')' : '' ?></a><?php endif; ?>
    </nav>
  </header>

  <main class="w-full">
    <?= $content_page ?? '' ?>
  </main>

  <!-- Footer -->
  <footer class="canva-footer bg-[#123d42] text-white mt-12">
    <div class="shell py-10 flex flex-wrap justify-between gap-8">
      <div class="max-w-sm">
        <p class="font-bold text-[22px] mb-2">WebShelf</p>
        <p class="text-sm text-[#c7d9d6] leading-relaxed">Một góc đọc dành cho những ý tưởng đáng giữ lại.</p>
      </div>
      <nav aria-label="Điều hướng chân trang" class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
        <a href="?r=home">Trang chủ</a>
        <a href="?r=library">Thư viện</a>
        <a href="?r=search">Tìm kiếm</a>
      </nav>
    </div>
    <div class="shell pb-7 text-xs text-[#a9c1bd]">
      © 2026 WebShelf · Dự án Web Archiving
    </div>
  </footer>

  <script>
    if (window.lucide) lucide.createIcons();
    document.getElementById('mobile-toggle')?.addEventListener('click', () => {
      const nav = document.getElementById('mobile-nav');
      if (nav) nav.hidden = !nav.hidden;
    });
  </script>
</body>
</html>
<?php

// -----------------------------------------------------------------------------
// 3. CÁC HÀM SUPPORT BACKEND
// -----------------------------------------------------------------------------
function store_snapshot(PDO $pdo, array $config, int $pageId, string $url): void { 
    $f = new Fetcher($config);
    $data = $f->archive($url);
    $v = (int)$pdo->query('SELECT COALESCE(MAX(version_no),0)+1 FROM snapshots WHERE saved_page_id='.$pageId)->fetchColumn();
    $title = (string)$pdo->query('SELECT title FROM saved_pages WHERE id='.$pageId)->fetchColumn();
    $q = $pdo->prepare('INSERT INTO snapshots(saved_page_id,version_no,title,clean_html,plain_text,http_status) VALUES(?,?,?,?,?,?)');
    $q->execute([$pageId, $v, $title !== '' ? $title : $data['title'], $data['html'], $data['text'], $data['status']]);
    $id = (int)$pdo->lastInsertId();
    $xp = new DOMXPath($data['dom']);
    $html = $f->saveImages($xp, $data['node'], $data['final_url'], $id, $pdo);
    $q = $pdo->prepare('UPDATE snapshots SET clean_html=? WHERE id=?');
    $q->execute([$html, $id]); 
}

function add_tag(PDO $pdo, int $lid, int $pid, string $name): void {
    $s = $pdo->prepare('INSERT INTO tags(library_id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $s->execute([$lid, $name]);
    $tid = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT IGNORE INTO saved_page_tags VALUES(?,?)')->execute([$pid, $tid]);
}