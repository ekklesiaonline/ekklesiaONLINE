<?php

namespace Treasurer\Controllers;

use App\Services\BankStatementParserService;

/**
 * BankingImport
 *
 * OFX/QFX/CSV bank statement import endpoint with duplicate detection and
 * the shared AJAX contract response shape. Extracted from Banking
 * (treasurer second pass).
 */
class BankingImport extends Banking
{

    /**
     * Import bank statement file (OFX/QFX/CSV) to bulk-populate transactions.
     *
     * @param int|string|null $accountId
     */
    public function importStatement($accountId = null)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $targetAccountId = (int) ($accountId ?: $this->request->getPost('bank_account_id'));
        if ($targetAccountId <= 0) {
            $err = lang('Finance.bankAccountRequired');
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 400, 'NO_ACCOUNT_SPECIFIED', $err);
            }
            return redirect()->back()->withInput()->with('error', $err);
        }

        $account = $this->banking->accountForParish($targetAccountId, $parishId);
        if ($account === null) {
            $err = lang('Finance.bankAccountNotFound');
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 404, 'ACCOUNT_NOT_FOUND', $err);
            }
            return redirect()->to('/banking')->with('error', $err);
        }

        $file = $this->request->getFile('statement_file');
        if ($file === null || ! $file->isValid()) {
            $err = lang('Finance.bankFileRequired');
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 422, 'FILE_REQUIRED', $err);
            }
            return redirect()->back()->withInput()->with('error', $err);
        }

        $ext = strtolower($file->getClientExtension());
        $allowedExtensions = ['ofx', 'qfx', 'csv', 'txt'];
        if (! in_array($ext, $allowedExtensions, true)) {
            $err = lang('Finance.bankInvalidFileType');
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 422, 'INVALID_FILE_TYPE', $err);
            }
            return redirect()->back()->withInput()->with('error', $err);
        }

        $parser = new BankStatementParserService();
        $transactions = $parser->parse($file->getTempName(), $file->getClientName());

        if (empty($transactions)) {
            $err = lang('Finance.bankImportNoTransactions');
            if ($this->request->isAJAX()) {
                return $this->contractResponse(false, 422, 'NO_TRANSACTIONS_FOUND', $err);
            }
            return redirect()->back()->withInput()->with('error', $err);
        }

        $result = $this->banking->importTransactions($targetAccountId, $transactions);

        $this->audit()->log('banking.statement.import', [
            'parish_id'   => $parishId,
            'entity_type' => 'bank_statement',
            'entity_id'   => $targetAccountId,
            'new_values'  => [
                'imported_count' => $result['imported'],
                'skipped_count'  => $result['skipped'],
            ],
            'meta'        => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'bank_account_id' => $targetAccountId,
                    'imported'        => $result['imported'],
                    'skipped'         => $result['skipped'],
                ]
            ),
        ]);

        $msg = lang('Finance.bankImportSuccess', [$result['imported'], $result['skipped']]);

        if ($this->request->isAJAX()) {
            return $this->contractResponse(true, 200, 'SUCCESS', $msg, [], [], [
                'imported'   => $result['imported'],
                'skipped'    => $result['skipped'],
                'account_id' => $targetAccountId,
            ]);
        }

        return redirect()->to('/banking/' . $targetAccountId)->with('success', $msg);
    }


    /**
     * Build an API/AJAX JSON contract response.
     *
     * @param array<string, mixed> $details
     * @param array<string, mixed> $errors
     * @param array<string, mixed>|null $data
     */
    private function contractResponse(
        bool $success,
        int $httpStatus,
        string $code,
        string $message,
        array $details = [],
        array $errors = [],
        ?array $data = null
    ) {
        $payload = [
            'success' => $success,
            'status'  => $success ? 'success' : 'error',
            'message' => $message,
            'meta'    => [
                'http_status' => $httpStatus,
                'ts'          => date('c'),
            ],
        ];

        if ($success) {
            if ($data !== null) {
                $payload['data'] = $data;
            }
        } else {
            $payload['error'] = [
                'code'    => $code,
                'message' => $message,
                'details' => $details,
            ];
            $payload['errors'] = $errors;
        }

        return $this->response->setStatusCode($httpStatus)->setJSON($payload);
    }
}
