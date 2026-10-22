<?php
declare(strict_types=1);

/**
 * Données de test de l'étape 3 (créées puis supprimées). Usage : php tests/step3_fixtures.php up|down|currency <CODE>|legal-publish|legal-draft
 * N'utilise que des opérations DML (compte applicatif suffisant). Tout est repérable par préfixe « test- » / « t3- ».
 */
require dirname(__DIR__) . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit; }

$pdo = db();
$cmd = $argv[1] ?? '';
$upl = GS_ROOT . '/uploads';

function jpeg(string $path, int $w, int $h, array $rgb): void
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    imagestring($im, 5, 20, 20, basename($path), imagecolorallocate($im, 255, 255, 255));
    imagejpeg($im, $path, 80);
}

function down(PDO $pdo, string $upl): void
{
    $pdo->exec("DELETE FROM projects WHERE slug LIKE 'test-%'");
    $pdo->exec("DELETE FROM products WHERE slug LIKE 'test-%'");
    $pdo->exec("DELETE FROM services WHERE slug LIKE 'test-%'");
    $pdo->exec("DELETE FROM testimonials WHERE author_name LIKE 'Test %'");
    $pdo->exec("DELETE FROM media WHERE stored_name LIKE 't3-%'");
    $pdo->exec("DELETE FROM categories WHERE scope = 'project' AND slug LIKE 'test-%'");
    $pdo->exec("UPDATE site_settings SET setting_value = '' WHERE setting_key = 'currency_code'");
    $pdo->exec("UPDATE pages SET status = 'draft', content = NULL WHERE slug IN ('mentions-legales','politique-de-confidentialite')");
    foreach (glob($upl . '/t3-*') ?: [] as $f) { unlink($f); }
}

switch ($cmd) {
    case 'up':
        down($pdo, $upl);
        $svc = (int) $pdo->query("SELECT id FROM services WHERE slug = 'broderie'")->fetchColumn();
        $cat = (int) $pdo->query("SELECT id FROM categories WHERE scope = 'project' AND slug = 'broderie'")->fetchColumn();
        $pdo->exec("INSERT INTO categories (scope, name, slug, sort_order) VALUES ('project', 'Test vide', 'test-vide', 99)");
        $media = [];
        foreach ([['t3-cover.jpg', 800, 600, [18, 52, 90]], ['t3-img1.jpg', 640, 480, [201, 42, 56]], ['t3-img2.jpg', 640, 480, [57, 169, 244]]] as [$n, $w, $h, $c]) {
            jpeg("$upl/$n", $w, $h, $c);
            $pdo->prepare('INSERT INTO media (stored_name, original_name, mime_type, size_bytes, width, height, alt_text) VALUES (?,?,?,?,?,?,?)')
                ->execute([$n, $n, 'image/jpeg', filesize("$upl/$n"), $w, $h, $n === 't3-cover.jpg' ? 'Couverture de test' : null]);
            $media[$n] = (int) $pdo->lastInsertId();
        }
        $ins = $pdo->prepare("INSERT INTO projects (service_id, category_id, slug, title, summary, description, client_name, completed_on, cover_media_id, status, published_at)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $evil = '<p>Texte légitime</p><script>alert("xss-desc")</script><img src="/uploads/t3-img1.jpg" onerror="alert(2)" alt="ok"><a href="javascript:alert(3)">piège</a>';
        $ins->execute([$svc, $cat, 'test-projet-riche', 'Projet <b>riche</b> & "test"', 'Résumé du projet de test', $evil, 'Client <i>Test</i>', '2026-03-05', $media['t3-cover.jpg'], 'published', '2026-09-01 10:00:00']);
        $rid = (int) $pdo->lastInsertId();
        $pi = $pdo->prepare('INSERT INTO project_images (project_id, media_id, caption, sort_order) VALUES (?,?,?,?)');
        $pi->execute([$rid, $media['t3-img1.jpg'], 'Légende <u>un</u>', 1]);
        $pi->execute([$rid, $media['t3-img2.jpg'], null, 2]);
        for ($i = 1; $i <= 14; $i++) {
            $ins->execute([$svc, $cat, sprintf('test-pag-%02d', $i), "Projet pagination $i", null, null, null, null, null, 'published', sprintf('2026-08-%02d 09:00:00', $i)]);
        }
        $ins->execute([$svc, $cat, 'test-brouillon', 'Projet brouillon SECRET', null, null, null, null, null, 'draft', null]);

        $pdo->prepare("INSERT INTO services (slug, name, short_description, status) VALUES ('test-service-brouillon','Service brouillon SECRET','x','draft')")->execute();
        $tp = $pdo->prepare('INSERT INTO products (service_id, slug, name, description, price_amount, price_note, status) VALUES (?,?,?,?,?,?,?)');
        $tp->execute([$svc, 'test-produit', 'Produit <b>test</b>', "Ligne 1\nLigne <script>alert(9)</script> 2", '1500.00', 'à partir de', 'published']);
        $tp->execute([$svc, 'test-produit-sur-devis', 'Produit sur devis', 'sans prix', null, null, 'published']);
        $tp->execute([$svc, 'test-produit-brouillon', 'Produit brouillon SECRET', 'x', '10', null, 'draft']);

        $tt = $pdo->prepare('INSERT INTO testimonials (author_name, author_role, content, status, reviewed_at) VALUES (?,?,?,?,?)');
        $tt->execute(['Test Approuvé', 'Directrice <b>test</b>', 'Témoignage validé de test.', 'approved', '2026-09-02 10:00:00']);
        $tt->execute(['Test EnAttente', null, 'Témoignage EN ATTENTE secret.', 'pending', null]);
        $tt->execute(['Test Rejeté', null, 'Témoignage REJETÉ secret.', 'rejected', '2026-09-02 11:00:00']);
        echo "fixtures créées\n";
        break;
    case 'down':
        down($pdo, $upl); echo "fixtures supprimées\n"; break;
    case 'currency':
        $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = 'currency_code'")->execute([$argv[2] ?? '']); break;
    case 'legal-publish':
        $pdo->prepare("UPDATE pages SET status = 'published', content = ? WHERE slug = 'mentions-legales'")
            ->execute(['<h2>Éditeur</h2><p>Texte de test</p><script>alert("xss-legal")</script><a href="javascript:alert(1)">x</a>']); break;
    case 'legal-draft':
        $pdo->exec("UPDATE pages SET status = 'draft', content = NULL WHERE slug = 'mentions-legales'"); break;
    default:
        fwrite(STDERR, "usage: up|down|currency CODE|legal-publish|legal-draft\n"); exit(1);
}
