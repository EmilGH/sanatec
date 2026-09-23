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

function team_identity_card(array $row, bool $self): void
{
    $tzs = DateTimeZone::listIdentifiers();
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-3">Identity</h2>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="name">Name</label>
          <input class="form-control" id="name" name="name" value="<?= e((string) ($row['name'] ?? '')) ?>" required autocomplete="name">
          <div class="form-text">One field — written however the person writes it.</div>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="date_of_birth">Date of birth</label>
          <input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="<?= e((string) ($row['date_of_birth'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="preferred_language">Language</label>
          <select class="form-select form-control" id="preferred_language" name="preferred_language">
            <?php foreach (LANGUAGES as $code => $l): ?>
              <option value="<?= e($code) ?>" <?= ($row['preferred_language'] ?? 'en') === $code ? 'selected' : '' ?>><?= e($l['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="nationality">Nationality</label>
          <input class="form-control" id="nationality" name="nationality" value="<?= e((string) ($row['nationality'] ?? '')) ?>" maxlength="2" pattern="[A-Za-z]{2}" placeholder="MX, US, DE" style="text-transform:uppercase">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="dan_number">DAN number</label>
          <input class="form-control" id="dan_number" name="dan_number" value="<?= e((string) ($row['dan_number'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="dan_expires_on">DAN expires</label>
          <input class="form-control" type="date" id="dan_expires_on" name="dan_expires_on" value="<?= e((string) ($row['dan_expires_on'] ?? '')) ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="timezone">Timezone</label>
          <select class="form-select form-control" id="timezone" name="timezone">
            <?php foreach ($tzs as $tz): ?>
              <option value="<?= e($tz) ?>" <?= ($row['timezone'] ?? 'America/Cancun') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if (!$self): ?>
        <div class="col-12 col-md-6">
          <label class="form-label" for="job_title">Job title</label>
          <input class="form-control" id="job_title" name="job_title" value="<?= e((string) ($row['job_title'] ?? '')) ?>" placeholder="Instructor, Cave guide, Office">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="started_on">Started</label>
          <input class="form-control" type="date" id="started_on" name="started_on" value="<?= e((string) ($row['started_on'] ?? '')) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label" for="ended_on">Ended</label>
          <input class="form-control" type="date" id="ended_on" name="ended_on" value="<?= e((string) ($row['ended_on'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label" for="internal_notes">Internal notes <span class="text-secondary fw-normal">— never shown publicly</span></label>
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
        <div class="col-12 col-md-4">
          <div class="text-secondary small mb-2">What they are</div>
          <?php foreach (TEAM_ROLES as $flag => $label): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="<?= $flag ?>" name="<?= $flag ?>" value="1" <?= !empty($row[$flag]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="<?= $flag ?>"><?= e($label) ?></label></div>
          <?php endforeach; ?>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-secondary small mb-2">What they may manage</div>
          <?php foreach (TEAM_PERMISSIONS as $flag => $label): ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="<?= $flag ?>" name="<?= $flag ?>" value="1" <?= !empty($row[$flag]) ? 'checked' : '' ?>>
              <label class="form-check-label" for="<?= $flag ?>"><?= e($label) ?></label></div>
          <?php endforeach; ?>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-secondary small mb-2">Account</div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= ($row['is_active'] ?? 1) ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
            <label class="form-check-label" for="is_active">Active — can sign in</label></div>
          <?php if ($isSelf): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
          <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="is_system_admin" name="is_system_admin" value="1" <?= !empty($row['is_system_admin']) ? 'checked' : '' ?> <?= $actorIsAdmin && !$isSelf ? '' : 'disabled' ?>>
            <label class="form-check-label" for="is_system_admin">System administrator <span class="text-secondary small d-block">Everything, always. Only an administrator can grant this.</span></label></div>
          <?php if (!empty($row['is_system_admin']) && !($actorIsAdmin && !$isSelf)): ?><input type="hidden" name="is_system_admin" value="1"><?php endif; ?>
        </div>
      </div>
    </div></div>
    <?php
}

function team_profile_card(array $row): void
{
    $langs = is_string($row['languages'] ?? null) ? implode(', ', json_decode($row['languages'], true) ?: []) : '';
    ?>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h6 text-aqua text-uppercase mb-1">Public profile</h2>
      <p class="text-secondary small mb-3">Shown on the website only while switched on. Off by default.</p>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="profile_public" name="profile_public" value="1" <?= !empty($row['profile_public']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="profile_public">Show my profile on the site</label>
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
          <div class="form-text">Two-letter codes, comma separated. Divers can look for a guide who speaks theirs.</div></div>
        <div class="col-12 col-md-6"><label class="form-label" for="public_slug">Profile address</label>
          <div class="input-group"><span class="input-group-text">/team/</span>
            <input class="form-control" id="public_slug" name="public_slug" value="<?= e((string) ($row['public_slug'] ?? '')) ?>" placeholder="made from the name if blank"></div></div>
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
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this credential?')"><?= csrf_field() ?><input type="hidden" name="action" value="cred_delete"><input type="hidden" name="cred_id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <form method="post" class="row g-2 align-items-end" id="cred-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="cred_save">
        <div class="col-6 col-md-2"><label class="form-label small">Agency</label>
          <select class="form-select form-control" name="agency"><?php foreach (CREDENTIAL_AGENCIES as $a): ?><option value="<?= e($a) ?>"><?= e($a) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label small">Type</label>
          <select class="form-select form-control" name="kind" id="cred-kind"><?php foreach (CREDENTIAL_TYPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 col-md-3"><label class="form-label small">Title</label><input class="form-control" name="title" placeholder="Open Water Scuba Instructor" required></div>
        <div class="col-6 col-md-2"><label class="form-label small">Number</label><input class="form-control" name="number"></div>
        <div class="col-6 col-md-2" id="cred-expiry"><label class="form-label small">Expiration</label><input class="form-control" type="date" name="expires_on"></div>
        <div class="col-12 col-md-auto d-flex gap-2 align-items-center ms-md-auto">
          <div class="form-check"><input class="form-check-input" type="checkbox" id="cr_verified" name="verified" value="1"><label class="form-check-label small" for="cr_verified">Seen</label></div>
          <button class="btn btn-sm btn-aqua ms-auto" type="submit">Add</button>
        </div>
      </form>
      <script>
      // Recreational cards do not expire: hide the date and clear it. The
      // server enforces the same rule; this just keeps the form honest.
      (function () {
        var kind = document.getElementById('cred-kind'), box = document.getElementById('cred-expiry');
        function sync() { var rec = kind.value === 'recreational'; box.style.display = rec ? 'none' : ''; box.querySelector('input').required = !rec; if (rec) box.querySelector('input').value = ''; }
        kind.addEventListener('change', sync); sync();
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

            case 'cred_save':
                if ($teamId === null) {
                    return true;
                }
                team_credential_save($teamId, null, $_POST, (int) ($actor['team']['id'] ?? 0) ?: null);
                audit('update', 'team_member', $teamId, 'credential added: ' . post('kind'));
                flash('Credential added.');
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
