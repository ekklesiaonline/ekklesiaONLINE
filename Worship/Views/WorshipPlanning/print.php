<!DOCTYPE html>
<html lang="<?= esc(service('request')->getLocale() ?: config('App')->defaultLocale) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('Worship.worshipPlan') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
        }

        h1 {
            margin-bottom: 6px;
        }

        .meta {
            color: #555;
            margin-bottom: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
            vertical-align: top;
        }

        th {
            background: #f4f4f4;
            text-align: left;
        }

        .small {
            color: #666;
            font-size: 12px;
        }

        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <h1><?= lang('Worship.serviceWorshipPlan') ?></h1>
    <div class="meta">
        <strong><?= lang('Worship.date') ?>:</strong> <?= esc(liturgy_format_long_date((string) ($service['service_date'] ?? ''))) ?><br>
        <strong><?= lang('Worship.time') ?>:</strong> <?= esc(liturgy_format_time((string) ($service['service_time'] ?? ''))) ?><br>
        <strong><?= lang('Worship.liturgicalDay') ?>:</strong> <?= esc(liturgical_day_label((string) ($service['liturgical_day'] ?? '')) ?: '-') ?>
    </div>

    <table>
        <thead>
            <tr>
                <th scope="col" style="width: 4%;">#</th>
                <th scope="col" style="width: 12%;"><?= lang('Worship.type') ?></th>
                <th scope="col" style="width: 34%;"><?= lang('Worship.title') ?></th>
                <th scope="col" style="width: 18%;"><?= lang('Worship.leader') ?></th>
                <th scope="col"><?= lang('Worship.notes') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $index => $item): ?>
                    <?php
                    $itemType = strtolower((string) ($item['item_type'] ?? 'other'));
                    $itemTypeKeyMap = [
                        'song' => 'Worship.itemTypeSong',
                        'reading' => 'Worship.itemTypeReading',
                        'prayer' => 'Worship.itemTypePrayer',
                        'sermon' => 'Worship.itemTypeSermon',
                        'announcement' => 'Worship.itemTypeAnnouncement',
                        'other' => 'Worship.itemTypeOther',
                    ];
                    $itemTypeKey = $itemTypeKeyMap[$itemType] ?? 'Worship.itemTypeOther';
                    ?>
                    <tr>
                        <td><?= (int) $index + 1 ?></td>
                        <td><?= esc(lang($itemTypeKey)) ?></td>
                        <td>
                            <?= esc((string) ($item['title'] ?? '')) ?>
                            <?php if (!empty($item['song_artist'])): ?>
                                <div class="small"><?= lang('Worship.artist') ?>: <?= esc((string) $item['song_artist']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= esc((string) ($item['leader_name'] ?? '-')) ?></td>
                        <td><?= esc((string) ($item['notes'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#777;"><?= lang('Worship.noWorshipPlanItems') ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
</body>

</html>