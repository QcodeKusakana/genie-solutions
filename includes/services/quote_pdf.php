<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/** Produit et télécharge une fiche PDF complète, sans dépendance Composer. */
function quote_pdf_download(int $quoteId): never
{
    admin_require('quotes.view');
    $stmt = db()->prepare('SELECT q.*,s.name AS service_name,p.name AS product_name,u.full_name AS assigned_name
        FROM quote_requests q LEFT JOIN services s ON s.id=q.service_id LEFT JOIN products p ON p.id=q.product_id
        LEFT JOIN users u ON u.id=q.assigned_to WHERE q.id=:id LIMIT 1');
    $stmt->execute(['id' => $quoteId]);
    $quote = $stmt->fetch();
    if (!$quote) render_error(404);

    $fetch = static function (string $sql) use ($quoteId): array {
        $query = db()->prepare($sql);
        $query->execute(['id' => $quoteId]);
        return $query->fetchAll();
    };
    $details = $fetch('SELECT field_key,field_value FROM quote_request_details WHERE quote_id=:id ORDER BY id');
    $attachments = $fetch('SELECT original_name,mime_type,size_bytes,created_at FROM quote_attachments WHERE quote_id=:id ORDER BY id');
    $history = $fetch('SELECT h.old_status,h.new_status,h.created_at,u.full_name FROM quote_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.quote_id=:id ORDER BY h.created_at,h.id');
    $notes = $fetch('SELECT n.note,n.created_at,u.full_name FROM quote_notes n LEFT JOIN users u ON u.id=n.user_id WHERE n.quote_id=:id ORDER BY n.created_at,n.id');

    $statuses = ADMIN_STATUSES;
    $detailLabels = [];
    foreach (quote_profiles() as $profile) foreach ($profile['fields'] as $field) $detailLabels[$field['key']] = $field['label'];
    $sections = [
        ['section', 'Coordonnées du demandeur'],
        ['pair', 'Nom complet', $quote['full_name']],
        ['pair', 'Organisation', $quote['organization'] ?: 'Non précisée'],
        ['pair', 'Téléphone', $quote['phone']],
        ['pair', 'Adresse e-mail', $quote['email']],
        ['section', 'Projet demandé'],
        ['pair', 'Service', $quote['service_name'] ?: 'Non précisé'],
        ['pair', 'Produit / prestation', $quote['product_name'] ?: 'Non précisé'],
        ['pair', 'Quantité estimée', $quote['quantity'] ?: 'Non précisée'],
        ['pair', 'Date souhaitée', $quote['desired_date'] ?: 'Non précisée'],
        ['pair', 'Livraison / retrait', $quote['delivery_mode'] ?: 'À préciser'],
        ['paragraph', 'Description du besoin', $quote['description']],
    ];
    if ($details) {
        $sections[] = ['section', 'Précisions du service'];
        foreach ($details as $detail) {
            $key = (string) $detail['field_key'];
            $sections[] = ['pair', $detailLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)), $detail['field_value']];
        }
    }
    $sections[] = ['section', 'Suivi de la demande'];
    $sections[] = ['pair', 'Statut actuel', $statuses[$quote['status']] ?? $quote['status']];
    $sections[] = ['pair', 'Reçue le', $quote['created_at']];
    $sections[] = ['pair', 'Dernière mise à jour', $quote['updated_at']];
    $sections[] = ['pair', 'Responsable', $quote['assigned_name'] ?: 'Non attribuée'];
    $sections[] = ['pair', 'Consentement enregistré le', $quote['consent_at']];
    $sections[] = ['pair', 'Notification e-mail', $quote['notified_at'] ?: 'Non envoyée / non configurée'];
    if ($attachments) {
        $sections[] = ['section', 'Pièces jointes - à télécharger séparément dans l’administration'];
        foreach ($attachments as $file) {
            $size = number_format((int) $file['size_bytes'] / 1024, 0, ',', ' ') . ' Ko';
            $sections[] = ['pair', 'Fichier joint', $file['original_name'] . ' · ' . $file['mime_type'] . ' · ' . $size . ' · reçu le ' . $file['created_at']];
        }
    }
    if ($history) {
        $sections[] = ['section', 'Historique des statuts'];
        foreach ($history as $item) {
            $from = $item['old_status'] ? ($statuses[$item['old_status']] ?? $item['old_status']) : 'Création';
            $to = $statuses[$item['new_status']] ?? $item['new_status'];
            $sections[] = ['pair', $item['created_at'], $from . ' → ' . $to . ' · ' . ($item['full_name'] ?: 'Demandeur')];
        }
    }
    if ($notes) {
        $sections[] = ['section', 'Notes internes - document confidentiel'];
        foreach ($notes as $note) $sections[] = ['paragraph', ($note['full_name'] ?: 'Équipe') . ' · ' . $note['created_at'], $note['note']];
    }

    $pdf = quote_pdf_render((string) $quote['reference'], $sections);
    $reference = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $quote['reference']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="demande-devis-' . $reference . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}

/** @param list<array> $sections */
function quote_pdf_render(string $reference, array $sections): string
{
    $pages = [];
    $commands = '';
    $y = 0.0;
    $pageHeight = 841.89;
    $blue = '0.07 0.20 0.35';
    $red = '0.79 0.16 0.22';
    $muted = '0.35 0.41 0.49';
    $newPage = static function (bool $first) use (&$pages, &$commands, &$y, $reference, $pageHeight, $blue, $red, $muted): void {
        if ($commands !== '') $pages[] = $commands;
        $commands = "q\n" . $blue . " rg\n0 " . ($pageHeight - 88) . " 595.28 88 re f\n";
        $commands .= quote_pdf_text_command(42, $pageHeight - 38, 'F2', 17, 'GÉNIE SOLUTIONS', '1 1 1');
        $commands .= quote_pdf_text_command(42, $pageHeight - 58, 'F1', 9, $first ? 'IMPRESSION · CONFECTION · BRODERIE' : 'FICHE DE DEMANDE DE DEVIS · SUITE', '0.86 0.91 0.97');
        $commands .= $red . " rg\n42 " . ($pageHeight - 76) . " 44 3 re f\nQ\n";
        if ($first) {
            $commands .= quote_pdf_text_command(42, $pageHeight - 119, 'F2', 15, 'FICHE DE DEMANDE DE DEVIS', $blue);
            $commands .= quote_pdf_text_command(42, $pageHeight - 140, 'F1', 10, 'Référence : ' . $reference, $muted);
            $y = $pageHeight - 165;
        } else {
            $commands .= quote_pdf_text_command(42, $pageHeight - 119, 'F2', 12, 'Demande ' . $reference, $blue);
            $y = $pageHeight - 145;
        }
    };
    $newPage(true);

    foreach ($sections as $item) {
        if ($item[0] === 'section') {
            if ($y < 82) $newPage(false);
            $y -= 5;
            $commands .= "q\n0.91 0.95 0.98 rg\n42 " . ($y - 17) . " 511 23 re f\nQ\n";
            $commands .= quote_pdf_text_command(51, $y - 10, 'F2', 9, (string) $item[1], $blue);
            $y -= 31;
            continue;
        }
        $paragraph = $item[0] === 'paragraph';
        $label = (string) $item[1];
        $wrapped = quote_pdf_wrap((string) $item[2], $paragraph ? 96 : 66);
        if ($paragraph) {
            $firstCapacity = max(1, min(40, (int) floor(($y - 58 - 29) / 13)));
            $chunks = [array_slice($wrapped, 0, $firstCapacity)];
            foreach (array_chunk(array_slice($wrapped, $firstCapacity), 40) as $nextChunk) $chunks[] = $nextChunk;
            foreach ($chunks as $chunkIndex => $chunk) {
                $height = count($chunk) * 13 + 29;
                if ($y - $height < 58) $newPage(false);
                $chunkLabel = $chunkIndex === 0 ? $label : $label . ' (suite)';
                $commands .= quote_pdf_text_command(48, $y - 9, 'F2', 9, $chunkLabel, '0.13 0.19 0.27');
                $lineY = $y - 25;
                foreach ($chunk as $line) {
                    $commands .= quote_pdf_text_command(48, $lineY, 'F1', 9, $line, '0.13 0.19 0.27');
                    $lineY -= 13;
                }
                $y -= $height;
            }
        } else {
            $height = max(18, count($wrapped) * 13 + 8);
            if ($y - $height < 58) $newPage(false);
            $commands .= quote_pdf_text_command(48, $y - 9, 'F2', 8, $label, $muted);
            $lineY = $y - 9;
            foreach ($wrapped as $line) {
                $commands .= quote_pdf_text_command(190, $lineY, 'F1', 9, $line, '0.13 0.19 0.27');
                $lineY -= 13;
            }
            $y -= $height;
        }
    }
    $pages[] = $commands;

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    ];
    $pageIds = [];
    foreach ($pages as $index => $content) {
        $pageId = 5 + ($index * 2);
        $streamId = $pageId + 1;
        $pageIds[] = $pageId . ' 0 R';
        $content .= "\n0.72 0.76 0.81 RG\n42 42 m 553 42 l S\n";
        $content .= quote_pdf_text_command(42, 27, 'F1', 8, 'Confidentiel · Génie Solutions', '0.35 0.41 0.49');
        $content .= quote_pdf_text_command(500, 27, 'F1', 8, ($index + 1) . ' / ' . count($pages), '0.35 0.41 0.49');
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $streamId . ' 0 R >>';
        $objects[$streamId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($pages) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $max = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= $max; $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id] ?? 0) . "\n";
    return $pdf . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}

function quote_pdf_text_command(float $x, float $y, string $font, int $size, string $text, string $color): string
{
    $text = str_replace(["\r", "\n", "\t"], ' ', $text);
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    if ($encoded === false) $encoded = '';
    $encoded = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $encoded) ?? '';
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    return sprintf("%s rg\nBT /%s %d Tf %.2f %.2f Td (%s) Tj ET\n", $color, $font, $size, $x, $y, $escaped);
}

/** @return list<string> */
function quote_pdf_wrap(string $text, int $limit): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') return ['—'];
    $words = preg_split('/\s+/u', $text) ?: [$text];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        while (mb_strlen($word, 'UTF-8') > $limit) {
            if ($line !== '') { $lines[] = $line; $line = ''; }
            $lines[] = mb_substr($word, 0, $limit, 'UTF-8');
            $word = mb_substr($word, $limit, null, 'UTF-8');
        }
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($candidate, 'UTF-8') > $limit && $line !== '') { $lines[] = $line; $line = $word; }
        else $line = $candidate;
    }
    if ($line !== '') $lines[] = $line;
    return $lines ?: ['—'];
}
