# Service Classification Reporting Queries

These queries summarize services by `service_family` (Holy Communion/Other) and `service_schedule` (Sunday/Weekday/Private).

## Monthly Summary (Service Counts)

```sql
SELECT
  YEAR(s.date)  AS year_num,
  MONTH(s.date) AS month_num,
  DATE_FORMAT(s.date, '%Y-%m') AS month_label,

  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday'  THEN 1 ELSE 0 END) AS hc_sunday_count,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday' THEN 1 ELSE 0 END) AS hc_weekday_count,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private' THEN 1 ELSE 0 END) AS hc_private_count,
  SUM(CASE WHEN sm.service_family = 'other'          AND sm.service_schedule = 'sunday'  THEN 1 ELSE 0 END) AS other_sunday_count,
  SUM(CASE WHEN sm.service_family = 'other'          AND sm.service_schedule = 'weekday' THEN 1 ELSE 0 END) AS other_weekday_count

FROM services s
JOIN service_metrics sm ON sm.service_id = s.id
WHERE s.church_id = :church_id
  AND s.date BETWEEN :from_date AND :to_date
GROUP BY YEAR(s.date), MONTH(s.date)
ORDER BY YEAR(s.date), MONTH(s.date);
```

## Yearly Summary (Service Counts)

```sql
SELECT
  YEAR(s.date) AS year_num,

  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday'  THEN 1 ELSE 0 END) AS hc_sunday_count,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday' THEN 1 ELSE 0 END) AS hc_weekday_count,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private' THEN 1 ELSE 0 END) AS hc_private_count,
  SUM(CASE WHEN sm.service_family = 'other'          AND sm.service_schedule = 'sunday'  THEN 1 ELSE 0 END) AS other_sunday_count,
  SUM(CASE WHEN sm.service_family = 'other'          AND sm.service_schedule = 'weekday' THEN 1 ELSE 0 END) AS other_weekday_count

FROM services s
JOIN service_metrics sm ON sm.service_id = s.id
WHERE s.church_id = :church_id
  AND YEAR(s.date) = :year
GROUP BY YEAR(s.date)
ORDER BY YEAR(s.date);
```

## Monthly Summary (Attendance Totals)

```sql
SELECT
  YEAR(s.date)  AS year_num,
  MONTH(s.date) AS month_num,
  DATE_FORMAT(s.date, '%Y-%m') AS month_label,

  -- Present counts
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS hc_sunday_present,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS hc_weekday_present,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS hc_private_present,
  SUM(CASE WHEN sm.service_family = 'other' AND sm.service_schedule = 'sunday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS other_sunday_present,
  SUM(CASE WHEN sm.service_family = 'other' AND sm.service_schedule = 'weekday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS other_weekday_present,

  -- Communion counts
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday'
           THEN COALESCE(sm.reported_communion_count, 0) ELSE 0 END) AS hc_sunday_communion,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday'
           THEN COALESCE(sm.reported_communion_count, 0) ELSE 0 END) AS hc_weekday_communion,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private'
           THEN COALESCE(sm.reported_communion_count, 0) ELSE 0 END) AS hc_private_communion

FROM services s
JOIN service_metrics sm ON sm.service_id = s.id
WHERE s.church_id = :church_id
  AND s.date BETWEEN :from_date AND :to_date
GROUP BY YEAR(s.date), MONTH(s.date)
ORDER BY YEAR(s.date), MONTH(s.date);
```

## Yearly Summary (Attendance Totals)

```sql
SELECT
  YEAR(s.date) AS year_num,

  -- Present counts
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS hc_sunday_present,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS hc_weekday_present,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS hc_private_present,
  SUM(CASE WHEN sm.service_family = 'other' AND sm.service_schedule = 'sunday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS other_sunday_present,
  SUM(CASE WHEN sm.service_family = 'other' AND sm.service_schedule = 'weekday'
           THEN COALESCE(sm.reported_present_count, 0) ELSE 0 END) AS other_weekday_present,

  -- Communion counts
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday'
           THEN COALESCE(sm.reported_communion_count, 0) ELSE 0 END) AS hc_sunday_communion,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday'
           THEN COALESCE(sm.reported_communion_count, 0) ELSE 0 END) AS hc_weekday_communion,
  SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private'
           THEN COALESCE(sm.reported_communion_count, 0) ELSE 0 END) AS hc_private_communion

FROM services s
JOIN service_metrics sm ON sm.service_id = s.id
WHERE s.church_id = :church_id
  AND YEAR(s.date) = :year
GROUP BY YEAR(s.date)
ORDER BY YEAR(s.date);
```

## CodeIgniter Model Method Example

```php
// In app/Models/ServiceModel.php or dedicated ReportsModel.php

public function getMonthlyServiceClassificationSummary(int $churchId, string $fromDate, string $toDate): array
{
    return $this->db->query("
        SELECT
            YEAR(s.date) AS year_num,
            MONTH(s.date) AS month_num,
            DATE_FORMAT(s.date, '%Y-%m') AS month_label,
            SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'sunday' THEN 1 ELSE 0 END) AS hc_sunday_count,
            SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'weekday' THEN 1 ELSE 0 END) AS hc_weekday_count,
            SUM(CASE WHEN sm.service_family = 'holy_communion' AND sm.service_schedule = 'private' THEN 1 ELSE 0 END) AS hc_private_count,
            SUM(CASE WHEN sm.service_family = 'other' AND sm.service_schedule = 'sunday' THEN 1 ELSE 0 END) AS other_sunday_count,
            SUM(CASE WHEN sm.service_family = 'other' AND sm.service_schedule = 'weekday' THEN 1 ELSE 0 END) AS other_weekday_count
        FROM services s
        JOIN service_metrics sm ON sm.service_id = s.id
        WHERE s.church_id = ?
          AND s.date BETWEEN ? AND ?
        GROUP BY YEAR(s.date), MONTH(s.date)
        ORDER BY YEAR(s.date), MONTH(s.date)
    ", [$churchId, $fromDate, $toDate])->getResultArray();
}
```

## Validation Rule

The controller enforces:

- **Private schedule is only valid for Holy Communion services**
- For "Other" services, only Sunday or Weekday schedules are allowed

This is validated in `app/Controllers/Services.php`:

```php
$serviceFamily = $this->request->getPost('service_family');
$serviceSchedule = $this->request->getPost('service_schedule');
if ($serviceSchedule === 'private' && $serviceFamily !== 'holy_communion') {
    $errors['service_schedule'] = 'Private services are only allowed for Holy Communion.';
}
```
