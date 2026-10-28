<?php
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(403); exit('CSRF không hợp lệ.'); } }
function login_required(): array { if (!user()) { header('Location: ?r=login'); exit; } return user(); }
function role(PDO $pdo, int $libraryId, int $userId): ?string { $s=$pdo->prepare('SELECT role FROM library_members WHERE library_id=? AND user_id=?'); $s->execute([$libraryId,$userId]); return $s->fetchColumn() ?: null; }
function allow(PDO $pdo, int $libraryId, array $roles): void { $u=login_required(); if (!in_array(role($pdo,$libraryId,(int)$u['id']), $roles, true)) { http_response_code(403); exit('Bạn không có quyền với thư viện này.'); } }
function page_library(PDO $pdo, int $pageId): array { $s=$pdo->prepare('SELECT p.*, l.name library_name FROM saved_pages p JOIN libraries l ON l.id=p.library_id WHERE p.id=?'); $s->execute([$pageId]); $p=$s->fetch(); if (!$p) { http_response_code(404); exit('Không tìm thấy bài lưu.'); } return $p; }
function snapshot(PDO $pdo, int $id): array { $s=$pdo->prepare('SELECT s.*,p.library_id,p.source_url,l.name library_name FROM snapshots s JOIN saved_pages p ON p.id=s.saved_page_id JOIN libraries l ON l.id=p.library_id WHERE s.id=?'); $s->execute([$id]); $x=$s->fetch(); if(!$x){http_response_code(404);exit('Không tìm thấy phiên bản.');} return $x; }
function library_tags(PDO $pdo, int $libraryId, int $pageId = 0): array {
    $sql = 'SELECT t.id,t.name,CASE WHEN spt.saved_page_id IS NULL THEN 0 ELSE 1 END selected
        FROM tags t LEFT JOIN saved_page_tags spt ON spt.tag_id=t.id AND spt.saved_page_id=?
        WHERE t.library_id=? ORDER BY t.name';
    $s = $pdo->prepare($sql);
    $s->execute([$pageId, $libraryId]);
    return $s->fetchAll();
}
function replace_page_tags(PDO $pdo, int $libraryId, int $pageId, string $input): void {
    $names = array_values(array_unique(array_filter(array_map(
        static fn (string $name): string => trim($name),
        preg_split('/[,;\n]+/', $input) ?: []
    ), static fn (string $name): bool => $name !== '')));
    foreach ($names as $name) {
        if (mb_strlen($name) > 60) {
            throw new RuntimeException('Mỗi nhãn không được dài quá 60 ký tự.');
        }
    }
    $pdo->prepare('DELETE spt FROM saved_page_tags spt JOIN tags t ON t.id=spt.tag_id WHERE spt.saved_page_id=? AND t.library_id=?')
        ->execute([$pageId, $libraryId]);
    foreach ($names as $name) {
        add_tag($pdo, $libraryId, $pageId, $name);
    }
}
function layout(string $title, string $body): never {
    $u=user(); $route=$_GET['r']??'home';
    $account=$u ? '<div class="account"><span class="avatar">'.h(mb_strtoupper(mb_substr($u['name'],0,1))).'</span><span>'.h($u['name']).'</span><a href="?r=logout">Đăng xuất</a></div>' : '<a class="login-link" href="?r=login">Đăng nhập</a>';
    $nav='<header class="archive-header"><div class="archive-top"><a class="archive-brand" href="?"><span class="archive-mark">▥</span><span>WEBSHELF<small>PERSONAL WEB ARCHIVE</small></span></a><nav class="collection-nav"><a class="'.($route==='home'?'active':'').'" href="?"><b>⌂</b>Trang chủ</a><a class="'.($route==='library'?'active':'').'" href="?r=library"><b>▦</b>Thư viện</a><a class="'.($route==='save'?'active':'').'" href="?r=save"><b>＋</b>Lưu trang</a><a class="'.($route==='search'?'active':'').'" href="?r=search"><b>⌕</b>Tìm kiếm</a></nav>'.$account.'</div><div class="archive-sub"><span>Kho lưu trữ cá nhân · đọc lại bất cứ lúc nào</span>'.($u?'<a href="?r=save">Lưu một trang mới</a>':'<a href="?r=login">Bắt đầu</a>').'</div></header>';
    $heading=$route==='home' ? '' : '<div class="page-heading"><div><p class="eyebrow">WEBSHELF ARCHIVE</p><h1>'.h($title).'</h1></div>'.($u&&$route!=='save'?'<a class="button button-primary quick-save" href="?r=save">＋ Lưu trang mới</a>':'').'</div>';
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).' · WebShelf</title><link rel="stylesheet" href="assets/styles.css?v=3"></head><body class="'.($u?'app':'guest').'">'.$nav.'<main class="content">'.$heading.$body.'</main><footer>WebShelf · Lưu lại tri thức công khai cho việc học và nghiên cứu.</footer></body></html>'; exit;
}
