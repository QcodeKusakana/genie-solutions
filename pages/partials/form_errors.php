<?php defined('GS_ROOT') || exit; ?>
<?php /** @var array<string,string> $errors  @var array<string,string> $labels  (clé de champ → libellé) */
if ($errors === []) { return; } ?>
<div class="alert alert-danger" id="form-errors" role="alert" tabindex="-1">
<?php if (isset($errors['_form'])): ?>
    <p class="mb-<?= count($errors) > 1 ? '2' : '0' ?>"><strong><?= e($errors['_form']) ?></strong></p>
<?php endif; ?>
<?php $fieldErrors = array_diff_key($errors, ['_form' => 1]); ?>
<?php if ($fieldErrors !== []): ?>
    <p class="mb-2"><strong><?= count($fieldErrors) > 1 ? 'Veuillez corriger les ' . count($fieldErrors) . ' champs suivants :' : 'Veuillez corriger le champ suivant :' ?></strong></p>
    <ul class="mb-0">
<?php foreach ($fieldErrors as $key => $msg): ?>
        <li><a class="alert-link" href="#f-<?= e($key) ?>"><?= e($labels[$key] ?? $key) ?></a> — <?= e($msg) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
