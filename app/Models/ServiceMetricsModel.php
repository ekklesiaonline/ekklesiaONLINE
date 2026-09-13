<?php

namespace App\Models;

use CodeIgniter\Model;

class ServiceMetricsModel extends Model
{
    protected $table            = 'service_metric';
    protected $primaryKey       = 'service_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'service_id',
        'format_of_service',
        'attendance_classification',
        'service_family',
        'service_schedule',
        'reported_present_count',
        'reported_communion_count',
        'online_live_views',
        'online_recorded_views_7d',
        'online_peak_viewers',
        'languages_json',
        'place_labels_json',
        // Sacramental counts (merged from service_sacramental_counts)
        'baptism_adult',
        'baptism_child',
        'confirmation_adult',
        'confirmation_teen',
        'reception_count',
        'marriage_count',
        'burial_count',
        'eucharist_count',
        'daily_office_count',
        // Parochial Report online viewer counts (merged from services table)
        'online_live_viewers',
        'online_recorded_views',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
