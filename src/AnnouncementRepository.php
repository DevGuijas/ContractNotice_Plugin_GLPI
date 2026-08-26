<?php

namespace GlpiPlugin\Contractnotice;

use DateTimeImmutable;
use DomainException;

final class AnnouncementRepository
{
    private const TARGET_ALL = 'all';
    private const TARGET_GROUPS = 'groups';
    private const TARGET_PROFILES = 'profiles';
    private const DELIVERY_IMMEDIATE = 'immediate';
    private const DELIVERY_LOGIN = 'login';

    public static function getAnnouncementsTable(): string
    {
        return 'glpi_plugin_contractnotice_announcements';
    }

    public static function getTargetsTable(): string
    {
        return 'glpi_plugin_contractnotice_targets';
    }

    /** Returns whether the plugin database schema is available. */
    public static function isInstalled(): bool
    {
        global $DB;

        return $DB->tableExists(self::getAnnouncementsTable())
            && $DB->tableExists(self::getTargetsTable());
    }

    /** @return array<int, array<string, mixed>> */
    public static function getForManagement(): array
    {
        global $DB;

        $announcements = [];
        foreach ($DB->request([
            'FROM' => self::getAnnouncementsTable(),
            'ORDER' => ['start_at DESC', 'id DESC'],
        ]) as $row) {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (bool) $row['is_active'];
            $announcements[$row['id']] = $row;
        }
        if ($announcements === []) {
            return [];
        }

        $targets = self::getTargetsByAnnouncement(array_keys($announcements));
        $groups = self::getGroups();
        $profiles = self::getProfiles();
        foreach ($announcements as $id => &$announcement) {
            $announcement['target_ids'] = $targets[$id]['ids'] ?? [];
            $announcement['target_type'] = $targets[$id]['type'] ?? self::TARGET_ALL;
            $announcement['audience_label'] = self::getAudienceLabel(
                $announcement['target_type'],
                $announcement['target_ids'],
                $groups,
                $profiles
            );
            $announcement['status'] = self::getStatus($announcement);
            $announcement['delivery_label'] = $announcement['delivery_mode'] === self::DELIVERY_IMMEDIATE
                ? __('Imediato', 'contractnotice')
                : __('Ao logar', 'contractnotice');
        }
        unset($announcement);

        return array_values($announcements);
    }

    /** @return array<string, mixed>|null */
    public static function get(int $id): ?array
    {
        foreach (self::getForManagement() as $announcement) {
            if ($announcement['id'] === $id) {
                return $announcement;
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    public static function getBlankFormData(): array
    {
        return [
            'id' => 0,
            'name' => '',
            'content' => '',
            'target_type' => self::TARGET_ALL,
            'target_ids' => [],
            'delivery_mode' => self::DELIVERY_IMMEDIATE,
            'start_at' => date('Y-m-d\TH:i'),
            'end_at' => '',
            'is_active' => true,
        ];
    }

    /** @return array<int, string> */
    public static function getGroups(): array
    {
        global $DB;

        $groups = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_groups',
            'ORDER' => ['name ASC'],
        ]) as $row) {
            $groups[(int) $row['id']] = (string) $row['name'];
        }
        return $groups;
    }

    /** @return array<int, string> */
    public static function getProfiles(): array
    {
        global $DB;

        $profiles = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_profiles',
            'ORDER' => ['name ASC'],
        ]) as $row) {
            $profiles[(int) $row['id']] = (string) $row['name'];
        }
        return $profiles;
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input, int $id = 0): int
    {
        global $DB;

        $announcement = self::validateInput($input);
        if ($announcement['is_active']) {
            self::assertNoAudienceConflict(
                $announcement['target_type'],
                $announcement['target_ids'],
                $announcement['start_at'],
                $announcement['end_at'],
                $id
            );
        }

        $now = date('Y-m-d H:i:s');
        $fields = [
            'name' => $announcement['name'],
            'content' => $announcement['content'],
            'target_type' => $announcement['target_type'],
            'delivery_mode' => $announcement['delivery_mode'],
            'start_at' => $announcement['start_at'],
            'end_at' => $announcement['end_at'],
            'is_active' => $announcement['is_active'] ? 1 : 0,
            'users_id' => (int) ($_SESSION['glpiID'] ?? 0),
            'date_mod' => $now,
        ];

        if ($id > 0) {
            if (self::get($id) === null) {
                throw new DomainException(__('Aviso não encontrado.', 'contractnotice'));
            }
            $DB->update(self::getAnnouncementsTable(), $fields, ['id' => $id]);
        } else {
            $fields['date_creation'] = $now;
            $DB->insert(self::getAnnouncementsTable(), $fields);
            $id = (int) $DB->insert_id();
        }

        self::replaceTargets($id, $announcement['target_type'], $announcement['target_ids']);
        return $id;
    }

    public static function toggle(int $id): void
    {
        global $DB;

        $announcement = self::get($id);
        if ($announcement === null) {
            throw new DomainException(__('Aviso não encontrado.', 'contractnotice'));
        }

        $willActivate = !$announcement['is_active'];
        if ($willActivate) {
            self::assertNoAudienceConflict(
                $announcement['target_type'],
                $announcement['target_ids'],
                $announcement['start_at'],
                $announcement['end_at'],
                $id
            );
        }
        $DB->update(self::getAnnouncementsTable(), [
            'is_active' => $willActivate ? 1 : 0,
            'date_mod' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        global $DB;

        $DB->delete(self::getTargetsTable(), ['plugin_contractnotice_announcements_id' => $id]);
        $DB->delete(self::getAnnouncementsTable(), ['id' => $id]);
    }

    /** @return array<int, array<string, string|int>> */
    public static function getForUser(int $userId, bool $pollOnly): array
    {
        $groups = self::getUserTargetIds('glpi_groups_users', 'groups_id', $userId);
        $profiles = self::getUserTargetIds('glpi_profiles_users', 'profiles_id', $userId);
        $now = date('Y-m-d H:i:s');
        $available = [];

        foreach (self::getForManagement() as $announcement) {
            if ($pollOnly && $announcement['delivery_mode'] !== self::DELIVERY_IMMEDIATE) {
                continue;
            }
            if (!self::isAvailableNow($announcement, $now)) {
                continue;
            }
            if (!self::matchesAudience($announcement, $groups, $profiles)) {
                continue;
            }
            $available[] = [
                'id' => $announcement['id'],
                'name' => $announcement['name'],
                'content' => $announcement['content'],
                'date_mod' => $announcement['date_mod'],
            ];
        }

        // Save-time validation forbids audience overlap. The limit is a final
        // guard so a user is never presented with multiple modals at once.
        return array_slice($available, 0, 1);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private static function validateInput(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $content = trim(strip_tags((string) ($input['content'] ?? '')));
        $targetType = (string) ($input['target_type'] ?? self::TARGET_ALL);
        $deliveryMode = (string) ($input['delivery_mode'] ?? self::DELIVERY_IMMEDIATE);
        $targetValues = match ($targetType) {
            self::TARGET_GROUPS => $input['group_target_ids'] ?? ($input['target_ids'] ?? []),
            self::TARGET_PROFILES => $input['profile_target_ids'] ?? ($input['target_ids'] ?? []),
            default => [],
        };
        $targetIds = self::normalizeIds($targetValues);
        $startAt = self::normalizeDate((string) ($input['start_at'] ?? ''));
        $endAt = self::normalizeDate((string) ($input['end_at'] ?? ''), true);

        if ($name === '' || $content === '') {
            throw new DomainException(__('Informe o título e a mensagem do aviso.', 'contractnotice'));
        }
        if (!in_array($targetType, [self::TARGET_ALL, self::TARGET_GROUPS, self::TARGET_PROFILES], true)) {
            throw new DomainException(__('Público-alvo inválido.', 'contractnotice'));
        }
        if (!in_array($deliveryMode, [self::DELIVERY_IMMEDIATE, self::DELIVERY_LOGIN], true)) {
            throw new DomainException(__('Tipo de disparo inválido.', 'contractnotice'));
        }
        if ($targetType === self::TARGET_ALL) {
            $targetIds = [];
        } elseif ($targetIds === []) {
            throw new DomainException(__('Selecione ao menos um grupo ou perfil.', 'contractnotice'));
        }
        if ($endAt !== null && $endAt < $startAt) {
            throw new DomainException(__('A data final deve ser posterior à data inicial.', 'contractnotice'));
        }

        return [
            'name' => $name,
            'content' => $content,
            'target_type' => $targetType,
            'target_ids' => $targetIds,
            'delivery_mode' => $deliveryMode,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'is_active' => isset($input['is_active']) && (string) $input['is_active'] !== '0',
        ];
    }

    /** @param array<int, int> $targetIds */
    private static function replaceTargets(int $announcementId, string $targetType, array $targetIds): void
    {
        global $DB;

        $DB->delete(self::getTargetsTable(), ['plugin_contractnotice_announcements_id' => $announcementId]);
        foreach ($targetIds === [] ? [0] : $targetIds as $targetId) {
            $DB->insert(self::getTargetsTable(), [
                'plugin_contractnotice_announcements_id' => $announcementId,
                'target_type' => $targetType,
                'targets_id' => $targetId,
            ]);
        }
    }

    /** @param array<int, int> $ids @return array<int, array{type: string, ids: array<int, int>}> */
    private static function getTargetsByAnnouncement(array $ids): array
    {
        global $DB;

        $targets = [];
        foreach ($DB->request([
            'FROM' => self::getTargetsTable(),
            'WHERE' => ['plugin_contractnotice_announcements_id' => $ids],
            'ORDER' => ['id ASC'],
        ]) as $row) {
            $announcementId = (int) $row['plugin_contractnotice_announcements_id'];
            $targets[$announcementId] ??= ['type' => (string) $row['target_type'], 'ids' => []];
            if ((int) $row['targets_id'] > 0) {
                $targets[$announcementId]['ids'][] = (int) $row['targets_id'];
            }
        }
        return $targets;
    }

    /** @param array<int, int> $candidateIds */
    private static function assertNoAudienceConflict(string $candidateType, array $candidateIds, string $candidateStart, ?string $candidateEnd, int $ignoredId): void
    {
        foreach (self::getForManagement() as $existing) {
            if ($existing['id'] === $ignoredId || !$existing['is_active']) {
                continue;
            }
            if (!self::schedulesOverlap($candidateStart, $candidateEnd, $existing['start_at'], $existing['end_at'])) {
                continue;
            }
            if (self::audiencesOverlap($candidateType, $candidateIds, $existing['target_type'], $existing['target_ids'])) {
                throw new DomainException(sprintf(
                    __('O público deste aviso conflita com o aviso ativo ou programado "%s". Desative-o, altere o período ou escolha outro público.', 'contractnotice'),
                    $existing['name']
                ));
            }
        }
    }

    /** @param array<int, int> $firstIds @param array<int, int> $secondIds */
    private static function audiencesOverlap(string $firstType, array $firstIds, string $secondType, array $secondIds): bool
    {
        if ($firstType === self::TARGET_ALL || $secondType === self::TARGET_ALL) {
            return true;
        }
        return array_intersect(
            self::getAudienceUserIds($firstType, $firstIds),
            self::getAudienceUserIds($secondType, $secondIds)
        ) !== [];
    }

    /** @param array<int, int> $targetIds @return array<int, int> */
    private static function getAudienceUserIds(string $targetType, array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }
        return self::getUsersForTargets(
            $targetType === self::TARGET_GROUPS ? 'glpi_groups_users' : 'glpi_profiles_users',
            $targetType === self::TARGET_GROUPS ? 'groups_id' : 'profiles_id',
            $targetIds
        );
    }

    private static function schedulesOverlap(string $startA, ?string $endA, string $startB, ?string $endB): bool
    {
        return ($endA === null || $startB <= $endA) && ($endB === null || $startA <= $endB);
    }

    /** @param array<string, mixed> $announcement */
    private static function isAvailableNow(array $announcement, string $now): bool
    {
        return $announcement['is_active'] && $announcement['start_at'] <= $now
            && ($announcement['end_at'] === null || $announcement['end_at'] === '' || $announcement['end_at'] >= $now);
    }

    /** @param array<string, mixed> $announcement @param array<int, int> $groups @param array<int, int> $profiles */
    private static function matchesAudience(array $announcement, array $groups, array $profiles): bool
    {
        return match ($announcement['target_type']) {
            self::TARGET_ALL => true,
            self::TARGET_GROUPS => array_intersect($announcement['target_ids'], $groups) !== [],
            self::TARGET_PROFILES => array_intersect($announcement['target_ids'], $profiles) !== [],
            default => false,
        };
    }

    /** @param array<int, int> $targetIds @return array<int, int> */
    private static function getUsersForTargets(string $table, string $field, array $targetIds): array
    {
        global $DB;

        $users = [];
        foreach ($DB->request([
            'SELECT' => ['users_id'],
            'FROM' => $table,
            'WHERE' => [$field => $targetIds],
        ]) as $row) {
            $users[] = (int) $row['users_id'];
        }
        return array_values(array_unique($users));
    }

    /** @return array<int, int> */
    private static function getUserTargetIds(string $table, string $field, int $userId): array
    {
        global $DB;

        $ids = [];
        foreach ($DB->request([
            'SELECT' => [$field],
            'FROM' => $table,
            'WHERE' => ['users_id' => $userId],
        ]) as $row) {
            $ids[] = (int) $row[$field];
        }
        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $announcement */
    private static function getStatus(array $announcement): string
    {
        $now = date('Y-m-d H:i:s');
        if (!$announcement['is_active']) {
            return __('Inativo', 'contractnotice');
        }
        if ($announcement['start_at'] > $now) {
            return __('Programado', 'contractnotice');
        }
        if ($announcement['end_at'] !== null && $announcement['end_at'] !== '' && $announcement['end_at'] < $now) {
            return __('Encerrado', 'contractnotice');
        }
        return __('Ativo', 'contractnotice');
    }

    /** @param array<int, int> $targetIds @param array<int, string> $groups @param array<int, string> $profiles */
    private static function getAudienceLabel(string $targetType, array $targetIds, array $groups, array $profiles): string
    {
        if ($targetType === self::TARGET_ALL) {
            return __('Todos os usuários', 'contractnotice');
        }
        $source = $targetType === self::TARGET_GROUPS ? $groups : $profiles;
        $names = array_map(static fn (int $id): string => $source[$id] ?? "#{$id}", $targetIds);
        $label = $targetType === self::TARGET_GROUPS ? __('Grupos', 'contractnotice') : __('Perfis', 'contractnotice');
        return $label . ': ' . implode(', ', $names);
    }

    /** @param mixed $value @return array<int, int> */
    private static function normalizeIds(mixed $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }
        $ids = array_map('intval', $value);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);
        return array_values(array_unique($ids));
    }

    private static function normalizeDate(string $value, bool $nullable = false): ?string
    {
        $value = trim($value);
        if ($value === '' && $nullable) {
            return null;
        }
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if ($date === false) {
            throw new DomainException(__('Data de programação inválida.', 'contractnotice'));
        }
        return $date->format('Y-m-d H:i:s');
    }
}
