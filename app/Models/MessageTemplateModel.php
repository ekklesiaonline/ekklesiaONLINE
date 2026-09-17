<?php

namespace App\Models;

class MessageTemplateModel extends BaseTenantModel
{
    protected $table            = 'message_template';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'name',
        'channel',
        'subject',
        'body',
        'is_active',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'parish_id' => 'required|is_not_unique[parish.id]',
        'name'      => 'required|max_length[120]',
        'channel'   => 'permit_empty|in_list[email,sms,notification,any]',
        'subject'   => 'permit_empty|max_length[255]',
        'body'      => 'required',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Active templates usable for a channel ('any' templates always qualify).
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeForParish(int $parishId, ?string $channel = null): array
    {
        $builder = $this->where('parish_id', $parishId)
            ->where('is_active', 1)
            ->orderBy('name', 'ASC');

        if ($channel !== null && $channel !== '') {
            $builder->whereIn('channel', [$channel, 'any']);
        }

        return $builder->findAll();
    }
}
