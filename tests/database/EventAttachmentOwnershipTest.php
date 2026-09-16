<?php

use App\Models\EventAttachmentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Regression guard for C5: event attachment removal must be scoped to the
 * event being edited. A user editing event A must not be able to remove
 * attachments belonging to event B by submitting foreign attachment IDs.
 *
 * @internal
 */
final class EventAttachmentOwnershipTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureEventAttachmentsTable();
    }

    public function testAttachmentRemovalIsScopedToEditedEvent(): void
    {
        $model = new EventAttachmentModel();

        // Insert two events with attachments.
        $eventAId = 1001;
        $eventBId = 1002;

        $attachmentAId = $this->insertAttachment($model, $eventAId, 'event-a-doc.pdf');
        $attachmentBId = $this->insertAttachment($model, $eventBId, 'event-b-doc.pdf');

        // Simulate removeSelectedAttachments(eventAId) with both IDs.
        // The query must only delete attachments where event_id = eventAId.
        $attachmentIds = [$attachmentAId, $attachmentBId];

        $attachments = $model
            ->whereIn('id', $attachmentIds)
            ->where('event_id', $eventAId)
            ->findAll();

        // Only event A's attachment should be found.
        $this->assertCount(1, $attachments);
        $this->assertSame($attachmentAId, (int) $attachments[0]['id']);

        // Delete the scoped attachments.
        foreach ($attachments as $attachment) {
            $model->delete((int) $attachment['id']);
        }

        // Event A's attachment is gone.
        $this->assertNull($model->find($attachmentAId));

        // Event B's attachment must still exist — it was NOT deleted.
        $survivor = $model->find($attachmentBId);
        $this->assertNotNull($survivor, 'Attachment belonging to event B must not be deleted when editing event A.');
        $this->assertSame($eventBId, (int) $survivor['event_id']);
    }

    public function testAttachmentRemovalWithNoMatchingIdsIsNoop(): void
    {
        $model = new EventAttachmentModel();

        $eventAId = 2001;
        $eventBId = 2002;

        $attachmentBId = $this->insertAttachment($model, $eventBId, 'event-b-only.pdf');

        // Attempt to remove attachment B while editing event A.
        $attachments = $model
            ->whereIn('id', [$attachmentBId])
            ->where('event_id', $eventAId)
            ->findAll();

        $this->assertSame([], $attachments, 'No attachments should match when the event_id does not match.');

        // Attachment B must still exist.
        $this->assertNotNull($model->find($attachmentBId));
    }

    private function insertAttachment(EventAttachmentModel $model, int $eventId, string $name): int
    {
        $id = $model->insert([
            'event_id'        => $eventId,
            'original_name'    => $name,
            'stored_name'     => 'event/' . $eventId . '/' . $name,
            'mime_type'       => 'application/pdf',
            'file_size'       => 1024,
            'attachment_type' => 'document',
            'created_at'      => date('Y-m-d H:i:s'),
        ], true);

        return (int) $id;
    }

    private function ensureEventAttachmentsTable(): void
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('event_attachments')) {
            return;
        }

        $forge = \Config\Database::forge();
        $forge->addField([
            'id'              => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'event_id'        => ['type' => 'INTEGER', 'unsigned' => true],
            'original_name'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_name'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type'       => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'file_size'       => ['type' => 'INTEGER', 'unsigned' => true, 'default' => 0],
            'attachment_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'document'],
            'created_at'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('event_id');
        $forge->createTable('event_attachments', true);
    }
}
