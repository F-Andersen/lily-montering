<?php
declare(strict_types=1);
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/uploads.php';

function post_text(string $key, int $max, bool $required = false): string
{
    if (!is_string($_POST[$key] ?? '')) throw new InvalidArgumentException('Ugyldig felt.');
    $value = trim($_POST[$key] ?? '');
    if (preg_match('//u', $value) !== 1 || str_contains($value, "\0") || mb_strlen($value, 'UTF-8') > $max || ($required && $value === '')) throw new InvalidArgumentException('Kontroller feltet: ' . $key . '.');
    return $value;
}

function record_data(string $kind, array $old): array
{
    $data = ['sort_order' => filter_var($_POST['sort_order'] ?? null, FILTER_VALIDATE_INT), 'active' => isset($_POST['active']) ? 1 : 0];
    if ($data['sort_order'] === false || abs($data['sort_order']) > 99999) throw new InvalidArgumentException('Ugyldig rekkefølge.');
    if ($kind !== 'gallery') {
        $data['title'] = post_text('title', 160, true);
        $data['slug'] = post_text('slug', 160);
        if ($data['slug'] === '') $data['slug'] = slugify($data['title']);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $data['slug'])) throw new InvalidArgumentException('URL-navnet kan bare ha små bokstaver, tall og bindestrek.');
    }
    if ($kind === 'services') {
        $data['category_id'] = (int)($_POST['category_id'] ?? 0);
        if (!query('SELECT id FROM service_categories WHERE id = ?', [$data['category_id']])->fetch()) throw new InvalidArgumentException('Velg en gyldig kategori.');
        foreach (['short_description' => 500, 'full_description' => 12000, 'price_text' => 160, 'duration_text' => 160, 'seo_title' => 160, 'seo_description' => 300] as $key => $max) $data[$key] = post_text($key, $max, in_array($key, ['short_description', 'full_description'], true));
        $data['featured'] = isset($_POST['featured']) ? 1 : 0;
    }
    if ($kind === 'gallery') {
        $data['caption'] = post_text('caption', 160);
        $data['alt_text'] = post_text('alt_text', 255, true);
        $data['featured'] = isset($_POST['featured']) ? 1 : 0;
    }
    if ($kind !== 'categories') {
        $data['image'] = $old['image'] ?? '';
        if (isset($_POST['remove_image'])) $data['image'] = '';
        if ($kind === 'gallery' && $data['image'] === '' && (($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) throw new InvalidArgumentException('Last opp et bilde.');
    }
    return $data;
}

function crud_page(string $kind): void
{
    require_admin();
    $tables = ['services' => 'services', 'categories' => 'service_categories', 'gallery' => 'gallery_items'];
    $titles = ['services' => 'Tjenester', 'categories' => 'Kategorier', 'gallery' => 'Bilder'];
    $table = $tables[$kind];
    $title = $titles[$kind];
    $base = 'admin/' . $kind . '/';
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
    $editing = isset($_GET['new']) || $id > 0;
    $error = '';
    $record = [];
    $newImage = '';
    try {
        if ($id) {
            $record = query('SELECT * FROM ' . $table . ' WHERE id = ?', [$id])->fetch();
            if (!$record) { http_response_code(404); admin_header($title); admin_error('Oppføringen finnes ikke.'); admin_footer(); return; }
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            require_post_csrf();
            if (($_POST['action'] ?? '') === 'delete') {
                if (!$id) throw new InvalidArgumentException('Velg en oppføring.');
                if ($kind === 'categories' && (int)query('SELECT COUNT(*) FROM services WHERE category_id = ?', [$id])->fetchColumn() > 0) throw new InvalidArgumentException('Kategorien er i bruk. Flytt tjenestene først.');
                query('DELETE FROM ' . $table . ' WHERE id = ?', [$id]);
                if ($kind !== 'categories') remove_unused_upload($record['image'] ?? '');
                flash('Oppføringen er slettet.');
                redirect($base);
            }
            $data = record_data($kind, $record);
            if ($kind !== 'gallery' && query('SELECT id FROM ' . $table . ' WHERE slug = ? AND id <> ?', [$data['slug'], $id])->fetch()) throw new InvalidArgumentException('URL-navnet er allerede i bruk.');
            if ($kind !== 'categories') {
                $newImage = upload_image('image_file');
                if ($newImage !== '') $data['image'] = $newImage;
            }
            $columns = array_keys($data);
            if ($id) query('UPDATE ' . $table . ' SET ' . implode(', ', array_map(static fn($c) => $c . ' = ?', $columns)) . ' WHERE id = ?', [...array_values($data), $id]);
            else query('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')', array_values($data));
            if ($kind !== 'categories' && ($record['image'] ?? '') !== ($data['image'] ?? '')) remove_unused_upload($record['image'] ?? '');
            flash('Endringene er lagret.');
            redirect($base);
        }
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
    catch (Throwable $ex) {
        safe_log('content update unavailable', $ex);
        $error = $ex instanceof PDOException && $ex->getCode() === '23000' ? 'URL-navnet er i bruk, eller kategorien har tjenester.' : 'Kunne ikke lagre. Prøv igjen senere.';
    }
    if ($newImage && $error) {
        try { remove_unused_upload($newImage); } catch (Throwable $ex) { safe_log('upload cleanup', $ex); }
    }
    if ($error && ($_POST['action'] ?? '') !== 'delete') {
        foreach ($_POST as $key => $value) if (is_string($value) && !in_array($key, ['csrf', 'action'], true)) $record[$key] = $value;
        foreach (['active','featured'] as $flag) $record[$flag] = isset($_POST[$flag]) ? 1 : 0;
    }
    admin_header($title);
    if ($error) admin_error($error);
    if ($editing && ($_POST['action'] ?? '') !== 'delete') {
        ?><a href="<?= e(url($base)) ?>">← Til oversikten</a><h2><?= $id ? 'Rediger' : 'Opprett' ?></h2>
<form class="edit-form" method="post" enctype="multipart/form-data"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
<?php
        if ($kind !== 'gallery') {
            admin_input('title', 'Tittel', $record['title'] ?? '', 'text', 160, true);
            admin_input('slug', 'URL-navn', $record['slug'] ?? '', 'text', 160);
        }
        if ($kind === 'services') {
            try { $categories = query('SELECT * FROM service_categories ORDER BY sort_order,id')->fetchAll(); }
            catch (Throwable $ex) { $categories = []; safe_log('categories unavailable', $ex); }
            foreach ($categories as $category) if ((int)$category['id'] === (int)($record['category_id'] ?? 0)) $record['category_slug'] = $category['slug'];
            ?><label class="field" for="category_id"><span>Kategori</span><select id="category_id" name="category_id" required><option value="">Velg kategori</option>
<?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($record['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?><?= $c['active'] ? '' : ' (inaktiv)' ?></option><?php endforeach ?>
</select></label>
<?php foreach (['short_description' => 'Kort beskrivelse', 'full_description' => 'Full beskrivelse'] as $key => $label): ?><label class="field" for="<?= $key ?>"><span><?= $label ?></span><textarea id="<?= $key ?>" name="<?= $key ?>" maxlength="<?= $key === 'short_description' ? 500 : 12000 ?>" required><?= e($record[$key] ?? '') ?></textarea></label><?php endforeach ?>
<div class="fields"><?php admin_input('price_text', 'Pris (valgfritt)', $record['price_text'] ?? ''); admin_input('duration_text', 'Varighet (valgfritt)', $record['duration_text'] ?? ''); ?></div>
<?php admin_input('seo_title', 'SEO-tittel', $record['seo_title'] ?? ''); admin_input('seo_description', 'SEO-beskrivelse', $record['seo_description'] ?? '', 'text', 300); ?>
<div class="search-preview" data-seo-preview data-suffix="<?= e(' i ' . settings()['service_region'] . ' | ' . settings()['company_name']) ?>"><p class="preview-url"><?= e(absolute_url(service_canonical_path($record['slug'] ?? ''))) ?></p><h3 data-preview-title><?= e(service_metadata($record + ['title' => '', 'seo_title' => '', 'seo_description' => '', 'short_description' => ''])['title']) ?></h3><p data-preview-description><?= e(service_metadata($record + ['title' => '', 'seo_title' => '', 'seo_description' => '', 'short_description' => ''])['description']) ?></p></div>
<?php
        }
        if ($kind === 'gallery') { admin_input('caption', 'Bildetekst', $record['caption'] ?? ''); admin_input('alt_text', 'Alternativ bildetekst (norsk)', $record['alt_text'] ?? '', 'text', 255, true); }
        if ($kind !== 'categories') {
            if ($kind === 'services' && $id > 0) {
                [$previewImage, $previewAlt] = service_media($record);
                if ($previewImage !== '') {
                    echo '<div class="image-preview-group">' . image_html($previewImage, $previewAlt, 'image-preview', true, '200px');
                    if ($previewImage !== ($record['image'] ?? '')) echo '<span class="field-meta">Standardbilde</span>';
                    echo '</div>';
                }
            } elseif (!empty($record['image'])) echo image_html($record['image'], $record['alt_text'] ?? $record['title'] ?? '', 'image-preview', true, '200px');
            ?><label class="field" for="image_file"><span>Bilde (JPEG, PNG, WebP, maks. 5 MB)</span><input id="image_file" name="image_file" type="file" accept="image/jpeg,image/png,image/webp" data-public-image aria-describedby="image-file-status"><span class="field-meta" id="image-file-status" data-upload-status role="status"></span></label>
<?php if ($kind === 'services'): ?><label class="check"><input type="checkbox" name="remove_image">Fjern bildet</label><?php endif ?>
<?php
        }
        admin_input('sort_order', 'Rekkefølge', $record['sort_order'] ?? 0, 'number');
        ?><div class="checks"><label class="check"><input name="active" type="checkbox" <?= ($record['active'] ?? 1) ? 'checked' : '' ?>>Aktiv</label>
<?php if ($kind !== 'categories'): ?><label class="check"><input name="featured" type="checkbox" <?= ($record['featured'] ?? 0) ? 'checked' : '' ?>>Vis på forsiden</label><?php endif ?>
</div><div class="form-actions"><button type="submit">Lagre</button><a href="<?= e(url($base)) ?>">Avbryt</a></div></form>
<?php
    } else {
        try {
            $search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 100) : '');
            $active = is_string($_GET['active'] ?? null) && in_array($_GET['active'], ['0', '1'], true) ? $_GET['active'] : '';
            $where = '1=1';
            $params = [];
            if ($active !== '') { $where .= ' AND active = ?'; $params[] = (int)$active; }
            if ($search !== '') {
                $columns = $kind === 'gallery' ? ['caption', 'alt_text'] : ['title', 'slug'];
                $where .= ' AND (' . implode(' OR ', array_map(static fn($column) => $column . " LIKE ? ESCAPE '!'", $columns)) . ')';
                $literal = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
                foreach ($columns as $column) $params[] = $literal;
            }
            $total = (int)query('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where, $params)->fetchColumn();
            [$page, $pages, $offset] = admin_page($total);
            $columns = $kind === 'categories' ? 'id,title,active,sort_order' : ($kind === 'gallery' ? 'id,image,caption,alt_text,active,featured,sort_order' : 'id,title,slug,image,category_id,(SELECT slug FROM service_categories WHERE id = services.category_id) AS category_slug,active,featured,sort_order');
            $rows = query('SELECT ' . $columns . ' FROM ' . $table . ' WHERE ' . $where . ' ORDER BY sort_order,id LIMIT 30 OFFSET ' . $offset, $params)->fetchAll();
            ?><form class="toolbar" method="get"><a class="action" href="<?= e(url($base . '?new=1')) ?>">Opprett ny</a><label class="field" for="q"><span>Søk</span><input id="q" type="search" name="q" maxlength="100" value="<?= e($search) ?>"></label><label class="field" for="active"><span>Status</span><select id="active" name="active"><option value="">Alle</option><option value="1" <?= $active === '1' ? 'selected' : '' ?>>Aktiv</option><option value="0" <?= $active === '0' ? 'selected' : '' ?>>Inaktiv</option></select></label><button type="submit">Søk</button><?php if ($search !== '' || $active !== ''): ?><a href="<?= e(url($base)) ?>">Nullstill</a><?php endif ?></form><p class="list-summary"><?= $total ?> oppføringer</p><?php
            ?><div class="table-scroll"><table><thead><tr><?php if ($kind !== 'categories'): ?><th>Bilde</th><?php endif ?><th><?= $kind === 'gallery' ? 'Bildetekst' : 'Tittel' ?></th><th>Status</th><th>Rekkefølge</th><th>Handlinger</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php if ($kind !== 'categories'): $media = $kind === 'services' ? service_media($r) : [$r['image'], $r['alt_text']]; ?><td class="media-cell"><?= image_html($media[0], $media[1], '', true, '64px') ?></td><?php endif ?>
<td><a href="<?= e(url($base . '?id=' . $r['id'])) ?>"><?= e($r['title'] ?? ($r['caption'] ?: $r['alt_text'])) ?></a></td>
<td><span class="status-badge <?= $r['active'] ? 'status-active' : 'status-inactive' ?>"><?= $r['active'] ? 'Aktiv' : 'Inaktiv' ?></span><?= !empty($r['featured']) ? ' · Forside' : '' ?></td><td><?= $r['sort_order'] ?></td>
<td><div class="actions"><a class="action secondary" href="<?= e(url($base . '?id=' . $r['id'])) ?>">Rediger</a><form method="post" action="<?= e(url($base)) ?>" data-confirm="Slette denne oppføringen?"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="danger" type="submit">Slett</button></form></div></td></tr><?php endforeach ?>
</tbody></table></div><?php admin_pagination($page, $pages, ['q' => $search, 'active' => $active]); ?>
<?php if (!$rows): ?><p>Ingen oppføringer.</p><?php endif ?>
<?php
        } catch (Throwable $ex) { safe_log('content list unavailable', $ex); admin_error('Kunne ikke laste innholdet.'); }
    }
    admin_footer();
}
