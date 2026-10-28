<?php
require dirname(__DIR__).'/src/bootstrap.php';
$r=$_GET['r']??'home';
require dirname(__DIR__).'/src/routes_extra.php';
if($r==='login'){
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $email=trim((string)($_POST['email']??''));
        $password=(string)($_POST['password']??'');
        $s=$pdo->prepare('SELECT id,name,email,password_hash FROM users WHERE email=?');
        $s->execute([$email]);
        $account=$s->fetch();
        if(!$account || !password_verify($password,(string)$account['password_hash'])){
            $error='Email hoặc mật khẩu không đúng.';
        }else{
            session_regenerate_id(true);
            $_SESSION['user']=['id'=>$account['id'],'name'=>$account['name'],'email'=>$account['email']];
            header('Location: ?');
            exit;
        }
    }
    $o='<h1>Đăng nhập</h1>'.(isset($error)?'<p class="error">'.h($error).'</p>':'')
        .'<form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'">'
        .'<label>Email <input type="email" name="email" required autocomplete="email"></label>'
        .'<label>Mật khẩu <input type="password" name="password" required autocomplete="current-password"></label>'
        .'<button type="submit">Đăng nhập</button></form>'
        .'<p class="muted">Chưa có tài khoản? <a href="?r=register">Đăng ký</a></p>';
    layout('Đăng nhập',$o);
}
if($r==='register'){
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $name=trim((string)($_POST['name']??''));
        $email=trim((string)($_POST['email']??''));
        $password=(string)($_POST['password']??'');
        $confirmation=(string)($_POST['password_confirmation']??'');
        if($name==='' || mb_strlen($name)>80){
            $error='Tên phải có từ 1 đến 80 ký tự.';
        }elseif(!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>120){
            $error='Email không hợp lệ.';
        }elseif(strlen($password)<8){
            $error='Mật khẩu phải có ít nhất 8 ký tự.';
        }elseif($password!==$confirmation){
            $error='Mật khẩu xác nhận không khớp.';
        }else{
            try{
                $insert=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)');
                $insert->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                session_regenerate_id(true);
                $_SESSION['user']=['id'=>(int)$pdo->lastInsertId(),'name'=>$name,'email'=>$email];
                header('Location: ?');
                exit;
            }catch(PDOException $e){
                if($e->getCode()==='23000'){
                    $error='Email này đã được đăng ký.';
                }else{
                    throw $e;
                }
            }
        }
    }
    $o='<h1>Đăng ký</h1>'.(isset($error)?'<p class="error">'.h($error).'</p>':'')
        .'<form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'">'
        .'<label>Họ tên <input name="name" required maxlength="80" autocomplete="name"></label>'
        .'<label>Email <input type="email" name="email" required maxlength="120" autocomplete="email"></label>'
        .'<label>Mật khẩu <input type="password" name="password" required minlength="8" autocomplete="new-password"></label>'
        .'<label>Nhập lại mật khẩu <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>'
        .'<button type="submit">Tạo tài khoản</button></form>'
        .'<p class="muted">Đã có tài khoản? <a href="?r=login">Đăng nhập</a></p>';
    layout('Đăng ký',$o);
}
if($r==='logout'){session_destroy();header('Location: ?r=login');exit;} $u=login_required();
if($r==='asset'){ $id=(int)($_GET['id']??0);$s=$pdo->prepare('SELECT a.*,p.library_id FROM assets a JOIN snapshots x ON x.id=a.snapshot_id JOIN saved_pages p ON p.id=x.saved_page_id WHERE a.id=?');$s->execute([$id]);$a=$s->fetch();if(!$a)exit;allow($pdo,(int)$a['library_id'],['owner','editor','viewer']);$path=__DIR__.'/uploads/'.$a['local_name'];if(!is_file($path)){http_response_code(404);exit;}header('Content-Type: '.$a['mime']);header('X-Content-Type-Options: nosniff');readfile($path);exit; }
if($r==='home'){ $s=$pdo->prepare('SELECT l.*,m.role FROM libraries l JOIN library_members m ON m.library_id=l.id WHERE m.user_id=?');$s->execute([$u['id']]);$libs=$s->fetchAll();$o='<section class="archive-hero"><div class="archive-emblem">▥</div><div><p class="eyebrow">THƯ VIỆN WEB CỦA BẠN</p><h1>Lưu những điều<br>bạn muốn nhớ.</h1><p>Đọc, tìm kiếm và giữ lại các trang web quan trọng — kể cả khi nguồn gốc đã thay đổi.</p><form class="archive-search" action="" method="get"><input type="hidden" name="r" value="search"><input name="q" required placeholder="Tìm trong kho lưu trữ của bạn"><button aria-label="Tìm kiếm">⌕</button></form></div></section><div class="section-title"><div><p class="eyebrow">BỘ SƯU TẬP</p><h2>Thư viện của tôi</h2></div><div class="actions"><a class="button" href="?r=library">Quản lý</a><a class="button button-primary" href="?r=save">＋ Lưu URL</a></div></div><div class="library-grid">';foreach($libs as $l){$q=$pdo->prepare('SELECT p.id,p.source_url,MAX(x.fetched_at) latest,MAX(x.id) snapshot_id FROM saved_pages p LEFT JOIN snapshots x ON x.saved_page_id=p.id WHERE p.library_id=? GROUP BY p.id ORDER BY latest DESC');$q->execute([$l['id']]);$pages=$q->fetchAll();$o.='<section class="card library-card"><div class="library-icon">▣</div><div><h2>'.h($l['name']).'</h2><span class="role-pill">'.h($l['role']).'</span></div><p class="muted">'.count($pages).' trang đã lưu</p><div class="saved-list">';foreach(array_slice($pages,0,3) as $p)$o.='<a href="?r=page&id='.$p['id'].'">'.h(parse_url($p['source_url'],PHP_URL_HOST) ?: $p['source_url']).'<small>'.h($p['latest']??'Đang xử lý').'</small></a>';$o.=(!$pages?'<span class="empty-note">Chưa có trang nào được lưu.</span>':'').'</div></section>';}$o.='</div><section class="advanced-search card"><div><p class="eyebrow">TÌM KIẾM NÂNG CAO</p><h2>Tìm theo nguồn hoặc nhãn</h2></div><form action="" method="get"><input type="hidden" name="r" value="search"><input name="q" required placeholder="Từ khóa"><input name="source" placeholder="URL nguồn"><input name="tag" placeholder="Nhãn, ví dụ PHP"><button>Tìm kiếm</button></form></section>';layout('Trang chủ',$o); }
if($r==='library'){
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $name=trim((string)($_POST['name']??''));
        if($name==='')$error='Tên thư viện là bắt buộc.';
        else{
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO libraries(name,owner_id) VALUES(?,?)')->execute([$name,$u['id']]);
            $lid=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO library_members VALUES(?,?,'owner')")->execute([$lid,$u['id']]);
            $pdo->commit();
            header('Location: ?r=library');
            exit;
        }
    }
    $s=$pdo->prepare('SELECT l.id,l.name,l.created_at FROM libraries l WHERE l.owner_id=? ORDER BY l.created_at DESC');
    $s->execute([$u['id']]);
    $o='<h1>Quản lý thư viện</h1>'.(isset($error)?'<p class="error">'.h($error).'</p>':'')
        .'<form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'"><label>Tên thư viện <input name="name" required maxlength="120"></label><button type="submit">Tạo thư viện</button></form>'
        .'<h2>Thư viện do tôi sở hữu</h2><div class="library-management">';
    foreach($s as $library){
        $o.='<section class="card library-management-item"><div><h3>'.h($library['name']).'</h3><small class="muted">Tạo lúc '.h($library['created_at']).'</small></div>'
            .'<p><a class="button" href="?r=members&library_id='.(int)$library['id'].'">Thành viên và nhãn</a></p>'
            .'<form method="post" action="?r=delete-library" onsubmit="return confirm(\'Xóa thư viện này cùng toàn bộ bài đã lưu, snapshot, ảnh và ghi chú?\');">'
            .'<input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="library_id" value="'.(int)$library['id'].'">'
            .'<button type="submit" class="button-danger">Xóa thư viện</button></form></section>';
    }
    $o.=($s->rowCount()===0?'<p class="empty-note">Bạn chưa sở hữu thư viện nào.</p>':'')
        .'</div><p class="muted">Chỉ owner được xóa thư viện. Thao tác này không thể hoàn tác.</p>';
    layout('Quản lý thư viện',$o);
}
if($r==='members'){
    $libraryId=(int)($_GET['library_id']??$_POST['library_id']??0);
    $library=$pdo->prepare('SELECT id,name FROM libraries WHERE id=? AND owner_id=?');
    $library->execute([$libraryId,$u['id']]);
    $library=$library->fetch();
    if(!$library){http_response_code(403);exit('Chỉ owner mới được quản lý thư viện này.');}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $action=(string)($_POST['action']??'');
        if($action==='member'){
            $email=trim((string)($_POST['email']??''));
            $memberRole=(string)($_POST['role']??'viewer');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($memberRole,['editor','viewer'],true)){
                $error='Email hoặc quyền truy cập không hợp lệ.';
            }else{
                $find=$pdo->prepare('SELECT id FROM users WHERE email=?');$find->execute([$email]);$memberId=$find->fetchColumn();
                if(!$memberId)$error='Không tìm thấy tài khoản với email này.';
                else{
                    $isOwner=$pdo->prepare('SELECT 1 FROM library_members WHERE library_id=? AND user_id=? AND role=?');$isOwner->execute([$libraryId,$memberId,'owner']);
                    if($isOwner->fetchColumn())$error='Không thể thay đổi quyền của owner.';
                    else $pdo->prepare('INSERT INTO library_members(library_id,user_id,role) VALUES(?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)')->execute([$libraryId,$memberId,$memberRole]);
                }
            }
        }elseif($action==='remove'){
            $memberId=(int)($_POST['user_id']??0);
            $pdo->prepare('DELETE FROM library_members WHERE library_id=? AND user_id=? AND role<>?')->execute([$libraryId,$memberId,'owner']);
        }elseif($action==='tag'){
            $tag=trim((string)($_POST['tag']??''));
            if($tag===''||mb_strlen($tag)>60)$error='Nhãn phải có từ 1 đến 60 ký tự.';
            else{$pdo->prepare('INSERT IGNORE INTO tags(library_id,name) VALUES(?,?)')->execute([$libraryId,$tag]);}
        }elseif($action==='delete-tag'){
            $pdo->prepare('DELETE FROM tags WHERE id=? AND library_id=?')->execute([(int)($_POST['tag_id']??0),$libraryId]);
        }
        if(!isset($error)){header('Location: ?r=members&library_id='.$libraryId);exit;}
    }
    $members=$pdo->prepare('SELECT u.id,u.name,u.email,m.role FROM library_members m JOIN users u ON u.id=m.user_id WHERE m.library_id=? ORDER BY FIELD(m.role,"owner","editor","viewer"),u.name');$members->execute([$libraryId]);
    $tags=$pdo->prepare('SELECT id,name FROM tags WHERE library_id=? ORDER BY name');$tags->execute([$libraryId]);
    $o='<h1>Quản lý '.h($library['name']).'</h1>'.(isset($error)?'<p class="error">'.h($error).'</p>':'')
      .'<section class="card"><h2>Thành viên và quyền</h2><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="library_id" value="'.$libraryId.'"><input type="hidden" name="action" value="member"><label>Email <input type="email" name="email" required></label><label>Quyền <select name="role"><option value="viewer">viewer — chỉ đọc</option><option value="editor">editor — lưu và refresh</option></select></label><button>Thêm hoặc cập nhật</button></form><ul>';
    foreach($members as $member){$o.='<li>'.h($member['name']).' ('.h($member['email']).') — <b>'.h($member['role']).'</b>';if($member['role']!=='owner')$o.='<form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="library_id" value="'.$libraryId.'"><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="'.(int)$member['id'].'"><button class="button-danger">Xóa</button></form>';$o.='</li>';}
    $o.='</ul></section><section class="card"><h2>Nhãn của thư viện</h2><form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="library_id" value="'.$libraryId.'"><input type="hidden" name="action" value="tag"><label>Nhãn <input name="tag" maxlength="60" required></label><button>Tạo nhãn</button></form><ul>';
    foreach($tags as $tagRow)$o.='<li>'.h($tagRow['name']).' <form method="post" style="display:inline"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="library_id" value="'.$libraryId.'"><input type="hidden" name="action" value="delete-tag"><input type="hidden" name="tag_id" value="'.(int)$tagRow['id'].'"><button class="button-danger">Xóa</button></form></li>';
    $o.='</ul></section>';layout('Thành viên và nhãn',$o);
}
if($r==='delete-library'){
    if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Phương thức không được hỗ trợ.');}
    verify_csrf();
    $libraryId=(int)($_POST['library_id']??0);
    $owner=$pdo->prepare('SELECT id FROM libraries WHERE id=? AND owner_id=?');
    $owner->execute([$libraryId,$u['id']]);
    if(!$owner->fetchColumn()){http_response_code(403);exit('Chỉ owner mới được xóa thư viện này.');}
    $assets=$pdo->prepare('SELECT a.local_name FROM assets a JOIN snapshots s ON s.id=a.snapshot_id JOIN saved_pages p ON p.id=s.saved_page_id WHERE p.library_id=?');
    $assets->execute([$libraryId]);
    $assetPaths=[];
    foreach($assets as $asset){
        $name=basename((string)$asset['local_name']);
        if($name!=='')$assetPaths[]=dirname(__DIR__).'/public/uploads/'.$name;
    }
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM libraries WHERE id=? AND owner_id=?')->execute([$libraryId,$u['id']]);
    $pdo->commit();
    foreach(array_unique($assetPaths) as $path){
        if(is_file($path) && !unlink($path))error_log('WebShelf: không thể xóa asset sau khi xóa thư viện: '.$path);
    }
    header('Location: ?r=library');
    exit;
}
if($r==='save'){
    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $lid=(int)($_POST['library_id']??0);
        allow($pdo,$lid,['owner','editor']);
        try{
            $pdo->beginTransaction();
            $q=$pdo->prepare('INSERT INTO saved_pages(library_id,source_url,created_by) VALUES(?,?,?)');
            $q->execute([$lid,trim((string)$_POST['url']),$u['id']]);
            $pid=(int)$pdo->lastInsertId();
            store_snapshot($pdo,$config,$pid,trim((string)$_POST['url']));
            replace_page_tags($pdo,$lid,$pid,(string)($_POST['tags']??''));
            $pdo->commit();
            header('Location: ?r=page&id='.$pid);
            exit;
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            $error=$e->getMessage();
        }
    }
    $s=$pdo->prepare("SELECT l.id,l.name FROM libraries l JOIN library_members m ON m.library_id=l.id WHERE m.user_id=? AND m.role IN ('owner','editor') ORDER BY l.name");
    $s->execute([$u['id']]);
    $libraries=$s->fetchAll();
    $o='<h1>Lưu bài viết</h1>'.(isset($error)?'<p class="error">'.h($error).'</p>':'');
    if($libraries===[]){
        $o.='<p class="error">Bạn chưa có thư viện được cấp quyền chỉnh sửa. Hãy tạo thư viện hoặc đăng nhập bằng tài khoản owner/editor.</p>';
    }else{
        $o.='<form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'"><label>Thư viện <select name="library_id" required>';
        foreach($libraries as $library)$o.='<option value="'.(int)$library['id'].'">'.h($library['name']).'</option>';
        $o.='</select></label><label>URL <input type="url" name="url" required placeholder="http://localhost/WebArchive/samples/site/article-01.html"></label><label>Nhãn (tùy chọn, phân cách bằng dấu phẩy) <input name="tags" maxlength="500"></label><button type="submit">Lấy và lưu</button></form>';
    }
    layout('Lưu URL',$o);
}
if($r==='page'){ $p=page_library($pdo,(int)$_GET['id']);allow($pdo,(int)$p['library_id'],['owner','editor','viewer']);$s=$pdo->prepare('SELECT id,version_no,title,fetched_at FROM snapshots WHERE saved_page_id=? ORDER BY version_no DESC');$s->execute([$p['id']]);$pageTags=library_tags($pdo,(int)$p['library_id'],(int)$p['id']);$tagNames=array_map(static fn(array $tag): string=>$tag['name'],array_filter($pageTags,static fn(array $tag):bool=>(int)$tag['selected']===1));$canEdit=in_array(role($pdo,(int)$p['library_id'],(int)$u['id']),['owner','editor'],true);$o='<h1>Phiên bản đã lưu</h1><p>Nguồn: <a href="'.h($p['source_url']).'" rel="noreferrer">'.h($p['source_url']).'</a></p><p>Nhãn: '.h($tagNames?implode(', ',$tagNames):'Chưa gán').'</p><div class="actions">';if($canEdit)$o.='<form method="post" action="?r=refresh&id='.$p['id'].'"><input type="hidden" name="csrf" value="'.csrf().'"><button>Lấy bản mới</button></form><a class="button" href="?r=edit-tags&id='.$p['id'].'">Sửa nhãn</a>';$o.='<a class="button" href="?r=export&id='.$p['id'].'">Xuất JSON</a><a class="button" href="?r=export-md&id='.$p['id'].'">Xuất Markdown</a></div><ul>';foreach($s as $x)$o.='<li><a href="?r=read&id='.$x['id'].'">v'.$x['version_no'].' — '.h($x['title']).'</a> <span class="muted">'.h($x['fetched_at']).'</span></li>';$o.='</ul>';layout('Bài lưu',$o); }
if($r==='edit-tags'){ $p=page_library($pdo,(int)$_GET['id']);allow($pdo,(int)$p['library_id'],['owner','editor']);if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();replace_page_tags($pdo,(int)$p['library_id'],(int)$p['id'],(string)($_POST['tags']??''));header('Location: ?r=page&id='.$p['id']);exit;}$selected=library_tags($pdo,(int)$p['library_id'],(int)$p['id']);$names=array_map(static fn(array $tag):string=>$tag['name'],array_filter($selected,static fn(array $tag):bool=>(int)$tag['selected']===1));$o='<form method="post" class="card"><input type="hidden" name="csrf" value="'.csrf().'"><label>Nhãn, phân cách bằng dấu phẩy <input name="tags" value="'.h(implode(', ',$names)).'" maxlength="500"></label><button>Lưu nhãn</button></form>';layout('Sửa nhãn',$o); }
if($r==='refresh'){verify_csrf();$p=page_library($pdo,(int)$_GET['id']);allow($pdo,(int)$p['library_id'],['owner','editor']);try{store_snapshot($pdo,$config,(int)$p['id'],$p['source_url']);header('Location: ?r=page&id='.$p['id']);}catch(Throwable $e){layout('Lỗi','<p class="error">'.h($e->getMessage()).'</p><p><a href="?r=page&id='.$p['id'].'">Quay lại</a></p>');}exit; }
if($r==='read'){ $x=snapshot($pdo,(int)$_GET['id']);allow($pdo,(int)$x['library_id'],['owner','editor','viewer']);if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$body=trim((string)($_POST['body']??''));if($body!==''){$s=$pdo->prepare('INSERT INTO notes(snapshot_id,user_id,body) VALUES(?,?,?)');$s->execute([$x['id'],$u['id'],$body]);}header('Location: ?r=read&id='.$x['id']);exit;}$s=$pdo->prepare('SELECT n.*,u.name FROM notes n JOIN users u ON u.id=n.user_id WHERE snapshot_id=? ORDER BY n.created_at');$s->execute([$x['id']]);$o='<p><a href="?r=page&id='.$x['saved_page_id'].'">← Các phiên bản</a> · v'.$x['version_no'].' · '.h($x['fetched_at']).'</p><article class="article"><h1>'.h($x['title']).'</h1>'.$x['clean_html'].'</article><section class="card"><h2>Ghi chú cho phiên bản này</h2>';foreach($s as $n){$o.='<p><b>'.h($n['name']).'</b>: '.nl2br(h($n['body']));if((int)$n['user_id']===(int)$u['id'])$o.=' <form method="post" action="?r=note" style="display:inline"><input type="hidden" name="csrf" value="'.csrf().'"><input type="hidden" name="snapshot_id" value="'.(int)$x['id'].'"><input type="hidden" name="note_id" value="'.(int)$n['id'].'"><input type="hidden" name="action" value="delete"><button class="button-danger">Xóa</button></form>'; $o.='</p>';}$o.='<form method="post"><input type="hidden" name="csrf" value="'.csrf().'"><textarea name="body" required maxlength="4000" placeholder="Ghi chú riêng cho phiên bản này"></textarea><br><button>Thêm ghi chú</button></form></section>';layout($x['title'],$o); }
if($r==='search'){
    $term=trim((string)($_GET['q']??''));
    $source=trim((string)($_GET['source']??''));
    $tag=trim((string)($_GET['tag']??''));
    $o='<form class="search-panel card" action="" method="get">'
        .'<input type="hidden" name="r" value="search">'
        .'<label>Từ khóa<input name="q" value="'.h($term).'" placeholder="Nhập từ khóa trong tiêu đề hoặc nội dung" autofocus></label>'
        .'<div class="search-filters"><label>Nguồn<input name="source" value="'.h($source).'" placeholder="Lọc theo URL nguồn"></label>'
        .'<label>Nhãn<input name="tag" value="'.h($tag).'" placeholder="Ví dụ: PHP"></label></div>'
        .'<button type="submit">Tìm kiếm</button></form>';
    if($term!==''||$source!==''||$tag!==''){
        $s=$pdo->prepare("SELECT DISTINCT x.id,x.title,x.plain_text,x.version_no,p.source_url FROM snapshots x JOIN saved_pages p ON p.id=x.saved_page_id JOIN library_members m ON m.library_id=p.library_id WHERE m.user_id=? AND (x.title LIKE ? OR x.plain_text LIKE ?) AND p.source_url LIKE ? AND (?='' OR EXISTS(SELECT 1 FROM saved_page_tags spt JOIN tags t ON t.id=spt.tag_id WHERE spt.saved_page_id=p.id AND t.name=?)) ORDER BY x.fetched_at DESC LIMIT 50");
        $like='%'.$term.'%';
        $s->execute([$u['id'],$like,$like,'%'.$source.'%',$tag,$tag]);
        $o.='<h2>Kết quả tìm “'.h($term).'”</h2>';
        foreach($s as $x){
            $at=$term===''?false:mb_stripos($x['plain_text'],$term);
            $snippet=$at===false && $term!=='' ? $x['title'].' — '.$x['plain_text'] : mb_substr($x['plain_text'],max(0,($at===false?0:$at)-70),220);
            $o.='<section class="card"><a href="?r=read&id='.$x['id'].'"><h2>'.h($x['title']).' (v'.$x['version_no'].')</h2></a><p class="snippet">…'.h($snippet).'…</p><small>'.h($x['source_url']).'</small></section>';
        }
        if(!$s->rowCount())$o.='<p class="empty-note">Không có kết quả phù hợp.</p>';
    } else {
        $o.='<p class="empty-note">Nhập từ khóa, nguồn hoặc nhãn để bắt đầu tìm kiếm trong các snapshot đã lưu.</p>';
    }
    layout('Tìm kiếm',$o);
}
if($r==='export'){ $p=page_library($pdo,(int)$_GET['id']);allow($pdo,(int)$p['library_id'],['owner','editor','viewer']);$s=$pdo->prepare('SELECT version_no,title,plain_text,fetched_at,http_status FROM snapshots WHERE saved_page_id=? ORDER BY version_no');$s->execute([$p['id']]);header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="webshelf-page-'.$p['id'].'.json"');echo json_encode(['source_url'=>$p['source_url'],'snapshots'=>$s->fetchAll()],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);exit; }
http_response_code(404);layout('404','Không tìm thấy trang.');

function store_snapshot(PDO $pdo,array $config,int $pageId,string $url): void { $f=new Fetcher($config);$data=$f->archive($url);$v=(int)$pdo->query('SELECT COALESCE(MAX(version_no),0)+1 FROM snapshots WHERE saved_page_id='.$pageId)->fetchColumn();$q=$pdo->prepare('INSERT INTO snapshots(saved_page_id,version_no,title,clean_html,plain_text,http_status) VALUES(?,?,?,?,?,?)');$q->execute([$pageId,$v,$data['title'],$data['html'],$data['text'],$data['status']]);$id=(int)$pdo->lastInsertId();$xp=new DOMXPath($data['dom']);$html=$f->saveImages($xp,$data['node'],$data['final_url'],$id,$pdo);$q=$pdo->prepare('UPDATE snapshots SET clean_html=? WHERE id=?');$q->execute([$html,$id]); }
function add_tag(PDO $pdo,int $lid,int $pid,string $name): void {$s=$pdo->prepare('INSERT INTO tags(library_id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$s->execute([$lid,$name]);$tid=(int)$pdo->lastInsertId();$pdo->prepare('INSERT IGNORE INTO saved_page_tags VALUES(?,?)')->execute([$pid,$tid]);}
