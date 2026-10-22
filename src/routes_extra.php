<?php

declare(strict_types=1);

if ($r === 'export-md') {
    $currentUser = login_required();
    $page = page_library($pdo, (int) ($_GET['id'] ?? 0));
    allow($pdo, (int) $page['library_id'], ['owner', 'editor', 'viewer']);
    $version = (int) ($_GET['version'] ?? 0);
    $query = $pdo->prepare(
        'SELECT * FROM snapshots WHERE saved_page_id=? AND (?=0 OR version_no=?) ORDER BY version_no'
    );
    $query->execute([$page['id'], $version, $version]);
    $snapshots = $query->fetchAll();
    if ($snapshots === []) {
        http_response_code(404);
        exit('Không tìm thấy phiên bản.');
    }

    $content = '# ' . $snapshots[0]['title'] . "\n\n"
        . '- Nguồn: ' . $page['source_url'] . "\n"
        . '- Thư viện: ' . $page['library_name'] . "\n\n";
    foreach ($snapshots as $snapshot) {
        $content .= '## Phiên bản ' . $snapshot['version_no'] . ' - ' . $snapshot['fetched_at'] . "\n\n"
            . strip_tags($snapshot['clean_html']) . "\n\n";
    }
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="webshelf-page-' . $page['id'] . '.md"');
    echo $content;
    exit;
}

if ($r === 'compare') {
    $currentUser = login_required();
    $page = page_library($pdo, (int) ($_GET['page'] ?? 0));
    allow($pdo, (int) $page['library_id'], ['owner', 'editor', 'viewer']);
    $first = (int) ($_GET['from'] ?? 0);
    $second = (int) ($_GET['to'] ?? 0);
    $query = $pdo->prepare(
        'SELECT * FROM snapshots WHERE saved_page_id=? AND id IN (?,?) ORDER BY version_no'
    );
    $query->execute([$page['id'], $first, $second]);
    $snapshots = $query->fetchAll();
    if (count($snapshots) !== 2 || $first === $second) {
        http_response_code(400);
        exit('Cần chọn hai phiên bản khác nhau của cùng bài viết.');
    }
    $body = '<h1>Đối chiếu phiên bản</h1><div class="comparison-grid">';
    foreach ($snapshots as $snapshot) {
        $body .= '<article class="card"><h2>v' . (int) $snapshot['version_no'] . '</h2>'
            . '<p class="muted">' . h($snapshot['fetched_at']) . '</p>'
            . '<div class="article">' . $snapshot['clean_html'] . '</div></article>';
    }
    $body .= '</div>';
    layout('Đối chiếu phiên bản', $body);
}

if ($r === 'note') {
    $currentUser = login_required();
    verify_csrf();
    $snapshotId = (int) ($_POST['snapshot_id'] ?? 0);
    $snapshotData = snapshot($pdo, $snapshotId);
    allow($pdo, (int) $snapshotData['library_id'], ['owner', 'editor', 'viewer']);
    $noteId = (int) ($_POST['note_id'] ?? 0);
    $action = $_POST['action'] ?? 'create';
    if ($action === 'delete') {
        $delete = $pdo->prepare('DELETE FROM notes WHERE id=? AND snapshot_id=? AND user_id=?');
        $delete->execute([$noteId, $snapshotId, $currentUser['id']]);
    } elseif ($action === 'update') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            http_response_code(422);
            exit('Ghi chú không được rỗng.');
        }
        $update = $pdo->prepare('UPDATE notes SET body=? WHERE id=? AND snapshot_id=? AND user_id=?');
        $update->execute([$body, $noteId, $snapshotId, $currentUser['id']]);
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body !== '') {
            $insert = $pdo->prepare('INSERT INTO notes(snapshot_id,user_id,body) VALUES(?,?,?)');
            $insert->execute([$snapshotId, $currentUser['id'], $body]);
        }
    }
    header('Location: ?r=read&id=' . $snapshotId);
    exit;
}
