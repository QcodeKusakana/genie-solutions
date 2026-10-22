<?php
declare(strict_types=1);

defined('GS_ROOT') || exit;

/**
 * Rendu accessible d'un champ de formulaire (libellé lié, aide et erreur reliées par aria-describedby).
 *
 * @param array{key:string,label:string,type?:string,required?:bool,max?:int,help?:string,options?:array<string,string>,autocomplete?:string,rows?:int,min?:string,max_date?:string} $f
 * @param array<string,string> $old    valeurs saisies
 * @param array<string,string> $errors erreurs par clé de champ
 */
function form_field(array $f, array $old, array $errors): string
{
    $key  = $f['key'];
    $id   = 'f-' . $key;
    $type = $f['type'] ?? 'text';
    $val  = (string) ($old[$key] ?? '');
    $err  = $errors[$key] ?? null;
    $req  = !empty($f['required']);

    $desc = [];
    if (!empty($f['help'])) { $desc[] = $id . '-help'; }
    if ($err !== null)      { $desc[] = $id . '-err'; }

    $attrs = ' id="' . e($id) . '" name="' . e($key) . '"'
        . ($req ? ' required' : '')
        . ($err !== null ? ' aria-invalid="true"' : '')
        . ($desc !== [] ? ' aria-describedby="' . e(implode(' ', $desc)) . '"' : '')
        . (!empty($f['autocomplete']) ? ' autocomplete="' . e($f['autocomplete']) . '"' : '');
    $cls = 'form-control' . ($err !== null ? ' is-invalid' : '');

    $html = '<div class="mb-3"><label class="form-label" for="' . e($id) . '">' . e($f['label'])
          . ($req ? '<span class="req" aria-hidden="true"> *</span><span class="visually-hidden"> (obligatoire)</span>' : '') . '</label>';

    switch ($type) {
        case 'textarea':
            $html .= '<textarea class="' . $cls . '" rows="' . (int) ($f['rows'] ?? 4) . '"' . $attrs
                  . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '') . '>' . e($val) . '</textarea>';
            break;
        case 'select':
            $html .= '<select class="form-select' . ($err !== null ? ' is-invalid' : '') . '"' . $attrs . '>';
            foreach ($f['options'] ?? [] as $v => $label) {
                $html .= '<option value="' . e((string) $v) . '"' . ((string) $v === $val ? ' selected' : '') . '>' . e($label) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'date':
            $html .= '<input class="' . $cls . '" type="date"' . $attrs . ' value="' . e($val) . '"'
                  . ' min="' . e($f['min'] ?? gmdate('Y-m-d')) . '" max="' . e($f['max_date'] ?? gmdate('Y-m-d', strtotime('+3 years'))) . '">';
            break;
        default: // text, email, tel
            $t = in_array($type, ['email', 'tel'], true) ? $type : 'text';
            $html .= '<input class="' . $cls . '" type="' . $t . '"' . $attrs . ' value="' . e($val) . '"'
                  . (isset($f['max']) ? ' maxlength="' . (int) $f['max'] . '"' : '') . ($t === 'tel' ? ' inputmode="tel"' : '') . '>';
    }

    if (!empty($f['help'])) { $html .= '<div class="form-text" id="' . e($id) . '-help">' . e($f['help']) . '</div>'; }
    if ($err !== null)      { $html .= '<div class="invalid-feedback d-block" id="' . e($id) . '-err">' . e($err) . '</div>'; }

    return $html . '</div>';
}
