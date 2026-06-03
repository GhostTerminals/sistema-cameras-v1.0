<?php
/**
 * Funcoes compartilhadas entre api_manutencao_cameras.php e api_manutencao_alarmes.php
 */

if (!function_exists('getInputPayload')) {
    function getInputPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return $_POST;
    }
}

if (!function_exists('getMaintenanceDefaultIds')) {
    function getMaintenanceDefaultIds(database $db): array
    {
        $statusId = null;
        $procedimentoId = null;

        $statusResult = $db->query("SELECT id FROM status WHERE UPPER(nome) = 'MANUTENCAO' LIMIT 1");
        if ($statusResult['status'] === 'success' && !empty($statusResult['data'])) {
            $statusId = (int)$statusResult['data'][0]->id;
        }

        $procedimentoResult = $db->query("SELECT id FROM procedimentos WHERE UPPER(nome) = 'MANUTENCAO' LIMIT 1");
        if ($procedimentoResult['status'] === 'success' && !empty($procedimentoResult['data'])) {
            $procedimentoId = (int)$procedimentoResult['data'][0]->id;
        }

        return [
            'status_id' => $statusId,
            'procedimento_id' => $procedimentoId,
        ];
    }
}
