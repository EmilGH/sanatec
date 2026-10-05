<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Form pieces shared by the team editor and a member's own profile page.
 * $self is true when a person edits their own record: no roles, permissions
 * or employment fields, and no "get sign-in link".
 */

function team_identity_card(array $row, bool $self, bool $isSelf = false): void
{
    $tzs = DateTimeZone::listIdentifiers();
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-3">Identity</h2>
      <div class="row g-3">
        <div class="col-12 <?= $self ? 'col-md-8' : 'col-md-7' ?>">
          <label class="form-label" for="name">Name</label>
          <input class="form-control" id="name" name="name" value="<?= e((string) ($row['name'] ?? '')) ?>" required autocomplete="name">
        </div>
        <?php if (!$self): ?>
        <div class="col-12 col-md-5">
          <label class="form-label" for="job_title">Job Title</label>
          <input class="form-control" id="job_title" name="job_title" value="<?= e((string) ($row['job_title'] ?? '')) ?>" placeholder="Instructor, Cave guide, Office">
        </div>
        <?php else: ?>
        <div class="col-6 col-md-4">
          <?php ui_date_field('date_of_birth', $row['date_of_birth'] ?? null, 'Date of Birth'); ?>
        </div>
        <?php endif; ?>

        <div class="col-6 col-md-4">
          <label class="form-label" for="nationality">Nationality</label>
          <?php ui_nationality_select('nationality', $row['nationality'] ?? null); ?>
        </div>
        <div class="col-6 col-md-4">
          <label class="form-label" for="preferred_language">Primary Language</label>
          <select class="form-select form-control" id="preferred_language" name="preferred_language">
            <?php foreach (LANGUAGES as $code => $l): ?>
              <option value="<?= e($code) ?>" <?= ($row['preferred_language'] ?? 'en') === $code ? 'selected' : '' ?>><?= e($l['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label" for="timezone">Timezone</label>
          <select class="form-select form-control" id="timezone" name="timezone">
            <?php foreach ($tzs as $tz): ?>
              <option value="<?= e($tz) ?>" <?= ($row['timezone'] ?? 'America/Cancun') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (!$self): ?>
        <div class="col-6 col-md-4">
          <?php ui_date_field('date_of_birth', $row['date_of_birth'] ?? null, 'Date of Birth'); ?>
        </div>
        <div class="col-6 col-md-4">
          <?php ui_date_field('started_on', $row['started_on'] ?? null, 'Date Started'); ?>
        </div>
        <?php /* ended_on stays in the database; leaving is not modelled yet, the Active switch covers it. */ ?>
        <input type="hidden" name="ended_on" value="<?= e((string) ($row['ended_on'] ?? '')) ?>">
        <div class="col-12 col-md-4">
          <label class="form-label d-none d-md-block" aria-hidden="true">&nbsp;</label>
          <div class="form-check form-switch st-switchrow">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= ($row['is_active'] ?? 1) ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
            <label class="form-check-label" for="is_active">Active Team Member</label>
          </div>
          <?php if ($isSelf): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="col-6">
          <label class="form-label" for="dan_number">DAN Number</label>
          <input class="form-control" id="dan_number" name="dan_number" value="<?= e((string) ($row['dan_number'] ?? '')) ?>">
        </div>
        <div class="col-6">
          <?php ui_date_field('dan_expires_on', $row['dan_expires_on'] ?? null, 'DAN Expiration'); ?>
        </div>

        <?php if (!$self): ?>
        <div class="col-12">
          <label class="form-label" for="internal_notes">Internal Notes <span class="text-secondary fw-normal">— never shown publicly</span></label>
          <textarea class="form-control" id="internal_notes" name="internal_notes" rows="2"><?= e((string) ($row['internal_notes'] ?? '')) ?></textarea>
        </div>
        <?php endif; ?>
      </div>
    </div></div>
    <?php
}

function team_roles_card(array $row, bool $actorIsAdmin, bool $isSelf): void
{
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-3">Roles and access</h2>
      <div class="row g-4">
        <div class="col-12 col-md-6">
          <div class="text-secondary small mb-2">Operating Roles</div>
          <?php foreach (TEAM_ROLES as $flag => $label): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="<?= $flag ?>" name="<?= $flag ?>" value="1" <?= !empty($row[$flag]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="<?= $flag ?>"><?= e($label) ?></label></div>
          <?php endforeach; ?>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-secondary small mb-2">System Access</div>
          <?php foreach (TEAM_PERMISSIONS as $flag => $label): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="<?= $flag ?>" name="<?= $flag ?>" value="1" <?= !empty($row[$flag]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="<?= $flag ?>"><?= e($label) ?></label></div>
          <?php endforeach; ?>
          <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="is_system_admin" name="is_system_admin" value="1" <?= !empty($row['is_system_admin']) ? 'checked' : '' ?> <?= $actorIsAdmin && !$isSelf ? '' : 'disabled' ?>>
            <label class="form-check-label" for="is_system_admin">System Administrator</label></div>
          <?php if (!empty($row['is_system_admin']) && !($actorIsAdmin && !$isSelf)): ?><input type="hidden" name="is_system_admin" value="1"><?php endif; ?>
        </div>
      </div>
    </div></div>
    <?php
}

function team_profile_card(array $row): void
{
    $langs = is_string($row['languages'] ?? null) ? implode(', ', json_decode($row['languages'], true) ?: []) : '';
    $tips = is_string($row['tip_handles'] ?? null) ? (json_decode($row['tip_handles'], true) ?: []) : [];
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-1">Public profile</h2>
      <p class="text-secondary small mb-3">Shown on the website only while switched on. Off by default.</p>
      <div class="d-flex flex-wrap gap-4 mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="profile_public" name="profile_public" value="1" <?= !empty($row['profile_public']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="profile_public">Show Profile on Team Page</label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="show_whatsapp_public" name="show_whatsapp_public" value="1" <?= !empty($row['show_whatsapp_public']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="show_whatsapp_public">Show WhatsApp on Team Page</label>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-12 col-md-6"><label class="form-label" for="title_en">Title <span class="text-aqua small">EN</span></label>
          <input class="form-control" id="title_en" name="title_en" value="<?= e((string) ($row['title_en'] ?? '')) ?>" placeholder="Cave Guide"></div>
        <div class="col-12 col-md-6"><label class="form-label" for="title_es">Title <span class="text-aqua small">ES</span></label>
          <input class="form-control" id="title_es" name="title_es" value="<?= e((string) ($row['title_es'] ?? '')) ?>" placeholder="Guía de cueva"></div>
        <div class="col-12 col-md-6"><label class="form-label" for="bio_en">Bio <span class="text-aqua small">EN</span></label>
          <textarea class="form-control" id="bio_en" name="bio_en" rows="4"><?= e((string) ($row['bio_en'] ?? '')) ?></textarea></div>
        <div class="col-12 col-md-6"><label class="form-label" for="bio_es">Bio <span class="text-aqua small">ES</span></label>
          <textarea class="form-control" id="bio_es" name="bio_es" rows="4"><?= e((string) ($row['bio_es'] ?? '')) ?></textarea></div>
        <div class="col-12 col-md-6"><label class="form-label" for="languages">Languages spoken</label>
          <input class="form-control" id="languages" name="languages" value="<?= e($langs) ?>" placeholder="es, en, de">
          <div class="form-text">Two-letter codes, comma separated.</div></div>
        <div class="col-12 col-md-6"><label class="form-label" for="public_slug">Profile address</label>
          <div class="input-group"><span class="input-group-text">/team/</span>
            <input class="form-control" id="public_slug" name="public_slug" value="<?= e((string) ($row['public_slug'] ?? '')) ?>" placeholder="made from the name if blank"></div></div>
      </div>
      <h3 class="h6 text-aqua text-uppercase mt-4 mb-1">Tips</h3>
      <p class="text-secondary small mb-3">Where divers can send a tip. Each one you fill in becomes a button on your public profile; leave the rest blank.</p>
      <div class="row g-3">
        <?php foreach (TIP_SERVICES as $code => [$label, $pattern, $hint]): ?>
        <div class="col-6 col-md-4"><label class="form-label" for="tip_<?= e($code) ?>"><?= e($label) ?></label>
          <input class="form-control" id="tip_<?= e($code) ?>" name="tip_<?= e($code) ?>" value="<?= e((string) ($tips[$code] ?? '')) ?>" autocomplete="off" autocapitalize="off" placeholder="<?= $pattern === null ? 'email or +1 mobile' : 'name' ?>">
          <div class="form-text"><?= $pattern !== null ? e(preg_replace('#^https://(www\.)?#', '', str_replace('%s', 'name', $pattern)) ?? '') : e($hint) ?></div></div>
        <?php endforeach; ?>
      </div>
    </div></div>
    <?php
}

/** Profile photo: its own form, because it uploads a file. */
function team_photo_card(array $row, string $postUrl): void
{
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-1">Photo</h2>
      <p class="text-secondary small mb-3">Shown on the public profile. Re-encoded on upload, so phone metadata such as location never leaves the server.</p>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <?php if (!empty($row['photo_path'])): ?><img src="/admin/team/photo.php?id=<?= (int) $row['id'] ?>&v=<?= e(substr(md5((string) $row['photo_path']), 0, 6)) ?>" alt="" width="96" height="96" style="border-radius:50%;object-fit:cover">
        <?php else: ?><span class="st-muted small">No photo yet.</span><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="photo_upload">
          <input class="form-control form-control-sm" type="file" name="photo" accept="image/*" required style="max-width:260px">
          <button class="btn btn-sm btn-outline-secondary" type="submit">Upload</button></form>
        <?php if (!empty($row['photo_path'])): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="photo_remove"><button class="btn btn-sm btn-outline-danger" type="submit">Remove</button></form><?php endif; ?>
      </div>
    </div></div>
    <?php
}

function team_channels_card(int $personId, string $postUrl): void
{
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-1">Contact channels</h2>
      <p class="text-secondary small mb-3">Any verified channel can receive a sign-in code. Mobiles are <code>+</code>country code then digits.</p>
      <div class="table-responsive"><table class="table table-sm align-middle mb-3">
        <tbody>
        <?php foreach (person_channels($personId) as $c): ?>
          <tr>
            <td class="text-nowrap"><i class="fa-solid <?= $c['kind'] === 'email' ? 'fa-envelope' : 'fa-mobile-screen' ?> fa-fw text-secondary me-1"></i><?= e($c['value']) ?>
              <?php if ($c['is_primary']): ?><span class="badge text-bg-info ms-1">primary</span><?php endif; ?></td>
            <td class="small text-secondary">
              <?= $c['verified_at'] ? '<i class="fa-solid fa-circle-check text-success"></i> verified' : '<i class="fa-regular fa-circle text-secondary"></i> unverified' ?>
              <?php if ($c['kind'] === 'mobile'): ?> · <?= $c['whatsapp_capable'] ? '<i class="fa-brands fa-whatsapp text-success"></i> WhatsApp' : 'no WhatsApp' ?><?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="channel_update"><input type="hidden" name="channel_id" value="<?= (int) $c['id'] ?>">
                <?php if (!$c['verified_at']): ?><button class="btn btn-sm btn-outline-secondary" name="set" value="verified" title="Mark verified">verify</button><?php endif; ?>
                <?php if ($c['kind'] === 'mobile'): ?><button class="btn btn-sm btn-outline-secondary" name="set" value="whatsapp_toggle" title="Toggle WhatsApp"><i class="fa-brands fa-whatsapp"></i></button><?php endif; ?>
                <?php if (!$c['is_primary']): ?><button class="btn btn-sm btn-outline-secondary" name="set" value="primary" title="Make primary">primary</button><?php endif; ?>
              </form>
              <form method="post" class="d-inline" onsubmit="return confirm('Remove <?= e(addslashes($c['value'])) ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="channel_delete"><input type="hidden" name="channel_id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="fa-solid fa-xmark"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?><input type="hidden" name="action" value="channel_add">
        <div class="col-5 col-md-3"><label class="form-label small" for="ch_kind">Type</label>
          <select class="form-select form-control" id="ch_kind" name="kind"><option value="email">Email</option><option value="mobile">Mobile</option></select></div>
        <div class="col-7 col-md-5"><label class="form-label small" for="ch_value">Address or number</label>
          <input class="form-control" id="ch_value" name="value" placeholder="name@example.com or +52…" required></div>
        <div class="col-12 col-md-4 d-flex gap-3 align-items-center">
          <div class="form-check"><input class="form-check-input" type="checkbox" id="ch_verified" name="verified" value="1"><label class="form-check-label small" for="ch_verified">Verified</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="ch_wa" name="whatsapp" value="1"><label class="form-check-label small" for="ch_wa">WhatsApp</label></div>
          <button class="btn btn-sm btn-aqua ms-auto" type="submit">Add</button>
        </div>
      </form>
    </div></div>
    <?php
}

function team_credentials_card(int $teamId): void
{
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-1">Credentials</h2>
      <p class="text-secondary small mb-3">The paperwork behind the roles. Anything with an expiry shows on the overview 60 days out.</p>
      <?php $creds = team_credentials($teamId); if ($creds !== []): ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-3">
        <thead><tr><th>Agency</th><th>Type</th><th>Title</th><th>Number</th><th>Expiration</th><th></th></tr></thead><tbody>
        <?php foreach ($creds as $c): $days = $c['days_left']; ?>
          <tr class="<?= $days !== null && (int) $days < 0 ? 'table-danger' : ($days !== null && (int) $days <= 60 ? 'table-warning' : '') ?>">
            <td><?= e($c['agency']) ?></td>
            <td><?= e(CREDENTIAL_TYPES[$c['kind']] ?? $c['kind']) ?></td>
            <td><?= e($c['title']) ?><?= $c['verified_at'] ? ' <i class="fa-solid fa-circle-check text-success" title="Verified against the original"></i>' : '' ?></td>
            <td class="text-secondary"><?= e((string) $c['number']) ?></td>
            <td class="text-nowrap"><?= $c['expires_on'] ? e($c['expires_on']) . ' <span class="small text-secondary">(' . ((int) $days < 0 ? abs((int) $days) . 'd ago' : (int) $days . 'd') . ')</span>' : '<span class="text-secondary">does not expire</span>' ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-secondary" type="button" title="Edit" data-cred-edit
                data-id="<?= (int) $c['id'] ?>" data-agency="<?= e($c['agency']) ?>" data-kind="<?= e($c['kind']) ?>" data-title="<?= e($c['title']) ?>" data-number="<?= e((string) $c['number']) ?>"
                data-expires="<?= $c['expires_on'] ? e(date('d/m/Y', strtotime($c['expires_on']))) : '' ?>" data-verified="<?= $c['verified_at'] ? '1' : '' ?>"><i class="fa-solid fa-pen"></i></button>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this credential?')"><?= csrf_field() ?><input type="hidden" name="action" value="cred_delete"><input type="hidden" name="cred_id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-xmark"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <form method="post" class="row g-2 align-items-end" id="cred-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="cred_save"><input type="hidden" name="cred_id" id="cred-id" value="">
        <div class="col-6 col-md-2"><label class="form-label small">Agency</label>
          <?php ui_agency_select('agency', null, ['empty' => '', 'id' => 'cred-agency']); ?></div>
        <div class="col-6 col-md-2"><label class="form-label small">Type</label>
          <select class="form-select form-control" name="kind" id="cred-kind"><?php foreach (CREDENTIAL_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><label class="form-label small">Title</label><input class="form-control" name="title" id="cred-title" placeholder="Open Water Scuba Instructor" required></div>
        <div class="col-6 col-md-2"><label class="form-label small">Number</label><input class="form-control" name="number" id="cred-number"></div>
        <div class="col-6 col-md-2" id="cred-expiry"><?php ui_date_field('expires_on', null, 'Expiration', false, '', ['id' => 'cred-expires', 'label_class' => 'small']); ?></div>
        <div class="col-12 col-md-auto d-flex gap-2 align-items-center ms-md-auto">
          <div class="form-check"><input class="form-check-input" type="checkbox" id="cr_verified" name="verified" value="1"><label class="form-check-label small" for="cr_verified">Seen</label></div>
          <a href="#" class="small text-secondary d-none" id="cred-cancel">Cancel</a>
          <button class="btn btn-sm btn-aqua ms-auto" type="submit" id="cred-submit">Add</button>
        </div>
      </form>
      <script>
      // Recreational cards do not expire: hide the date and clear it. The
      // server enforces the same rule; this just keeps the form honest.
      // Edit loads a row into this same form; Cancel puts it back to "Add".
      (function () {
        var form = document.getElementById('cred-form'), kind = document.getElementById('cred-kind'), box = document.getElementById('cred-expiry');
        var $ = function (id) { return document.getElementById(id); };
        function sync() { var rec = kind.value === 'recreational'; box.style.display = rec ? 'none' : ''; box.querySelector('input').required = !rec; if (rec) box.querySelector('input').value = ''; }
        function reset() { form.reset(); $('cred-id').value = ''; $('cred-submit').textContent = 'Add'; $('cred-cancel').classList.add('d-none'); sync(); }
        kind.addEventListener('change', sync); sync();
        document.querySelectorAll('[data-cred-edit]').forEach(function (b) {
          b.addEventListener('click', function () {
            var d = b.dataset;
            $('cred-id').value = d.id; $('cred-agency').value = d.agency; kind.value = d.kind; $('cred-title').value = d.title;
            $('cred-number').value = d.number; $('cred-expires').value = d.expires; $('cr_verified').checked = d.verified === '1';
            $('cred-submit').textContent = 'Save changes'; $('cred-cancel').classList.remove('d-none'); sync();
            form.scrollIntoView({ behavior: 'smooth', block: 'center' }); $('cred-title').focus();
          });
        });
        $('cred-cancel').addEventListener('click', function (e) { e.preventDefault(); reset(); });
      })();
      </script>
    </div></div>
    <?php
}

/** Shared handling of channel and credential sub-forms. Returns true if it handled the action. */
function team_handle_subforms(string $action, int $personId, ?int $teamId, array $actor): bool
{
    try {
        switch ($action) {
            case 'channel_add':
                channel_upsert($personId, post('kind'), post('value'), ['verified' => isset($_POST['verified']), 'whatsapp' => isset($_POST['whatsapp'])]);
                audit('update', 'person', $personId, 'channel added: ' . post('kind'));
                flash('Channel added.');
                return true;

            case 'channel_update':
                $cid = (int) ($_POST['channel_id'] ?? 0);
                $set = post('set');
                $stmt = db()->prepare('SELECT * FROM contact_channels WHERE id = :id AND person_id = :p');
                $stmt->execute([':id' => $cid, ':p' => $personId]);
                $c = $stmt->fetch();
                if (!$c) {
                    return true;
                }
                if ($set === 'verified') {
                    db()->prepare('UPDATE contact_channels SET verified_at = NOW() WHERE id = :id')->execute([':id' => $cid]);
                } elseif ($set === 'whatsapp_toggle' && $c['kind'] === 'mobile') {
                    db()->prepare('UPDATE contact_channels SET whatsapp_capable = 1 - whatsapp_capable, whatsapp_checked_at = NOW() WHERE id = :id')->execute([':id' => $cid]);
                } elseif ($set === 'primary') {
                    channel_upsert($personId, $c['kind'], $c['value'], ['primary' => true, 'whatsapp' => (bool) $c['whatsapp_capable']]);
                }
                flash('Channel updated.');
                return true;

            case 'channel_delete':
                channel_delete($personId, (int) ($_POST['channel_id'] ?? 0));
                audit('update', 'person', $personId, 'channel removed');
                flash('Channel removed.');
                return true;

            case 'photo_upload':
                if ($teamId === null) {
                    return true;
                }
                team_photo_set($teamId, $_FILES['photo'] ?? null);
                audit('update', 'team_member', $teamId, 'photo changed');
                flash('Photo saved.');
                return true;

            case 'photo_remove':
                if ($teamId === null) {
                    return true;
                }
                team_photo_set($teamId, null);
                flash('Photo removed.');
                return true;

            case 'cred_save':
                if ($teamId === null) {
                    return true;
                }
                $credId = (int) ($_POST['cred_id'] ?? 0) ?: null;
                team_credential_save($teamId, $credId, $_POST, (int) ($actor['team']['id'] ?? 0) ?: null);
                audit('update', 'team_member', $teamId, 'credential ' . ($credId ? 'edited' : 'added') . ': ' . post('kind'));
                flash($credId ? 'Credential saved.' : 'Credential added.');
                return true;

            case 'cred_delete':
                if ($teamId === null) {
                    return true;
                }
                team_credential_delete($teamId, (int) ($_POST['cred_id'] ?? 0));
                flash('Credential deleted.');
                return true;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
        return true;
    }

    return false;
}
