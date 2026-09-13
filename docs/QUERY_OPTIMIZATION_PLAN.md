# Database Query Optimization Review & Implementation Plan

**Date:** April 1, 2026  
**Status:** Implementation Phase  
**Priority:** MEDIUM (Ensure scalability as data grows)

---

## 1. Executive Summary

Analysis of codebase identified multiple optimization opportunities:

| Category | Issues Found | Impact | Priority |
|----------|--------------|--------|----------|
| **N+1 Queries** | 5 critical, 8 medium | Report generation slowdown, bulk operations | CRITICAL |
| **Missing Pagination** | 12+ result sets | Memory bloat with large data volumes | HIGH |
| **Eager Loading** | Inconsistent joins in 6 controllers | Possible duplicate queries | MEDIUM |
| **Caching Opportunities** | 3 high-access datasets | Repeated role/permission queries | MEDIUM |
| **Missing Indexes** | Estimated 4-5 on frequently queried columns | Query plan inefficiencies | HIGH |

**Estimated Performance Gain:** 40-60% faster for bulk operations; 20-30% faster for dashboards

---

## 2. Critical Issues (Fix Immediately)

### 2.1 N+1 Query: Member Transfer Operation

**Location:** [Pastor/Controllers/Members.php](Pastor/Controllers/Members.php#L539-L570)

**Problem:**

```php
foreach ($members as $member) {
    $userId = (int) ($member['user_id'] ?? 0);
    // Query 1: isUserAffiliatedWithParish (inside loop)
    if ($userId <= 0 || !$affiliationModel->isUserAffiliatedWithParish($userId, $sourceChurchId, true)) {
        // ...
    }
}

foreach ($members as $member) {
    // Query 2: member_church_affiliations per member inside loop
    $sourceAffiliation = $db->table('member_church_affiliations')
        ->where('user_id', $userId)
        ->where('church_id', $sourceChurchId)
        ->where('active', 1)
        ->get()
        ->getRowArray();
}
```

**Impact:** Transferring 100 members = 200+ queries

**Fix:** Fetch all affiliations upfront in single query, then loop through in-memory

```php
$allAffiliations = $db->table('member_church_affiliations')
    ->whereIn('user_id', $memberUserIds)
    ->where('church_id', $sourceChurchId)
    ->where('active', 1)
    ->get()
    ->getResultArray();

$affiliationMap = [];
foreach ($allAffiliations as $aff) {
    $affiliationMap[(int)$aff['user_id']] = $aff;
}

foreach ($members as $member) {
    $userId = (int) ($member['user_id'] ?? 0);
    $sourceAffiliation = $affiliationMap[$userId] ?? null; // O(1) lookup
}
```

### 2.2 N+1 Query: Standing Refresh (Province/Diocese Scope)

**Location:** [MemberPortal/Controllers/Standing.php](MemberPortal/Controllers/Standing.php#L50-L80)

**Problem:**

```php
$churchRows = db_connect()->table('churches c')
    ->select('c.id')
    ->join('dioceses d', 'd.id = c.diocese_id', 'inner')
    ->where('d.province_id', $provinceId)
    ->get()
    ->getResultArray();

foreach ($churchRows as $row) {
    // Query per church (N queries where N = church count)
    $count += $standingModel->refreshForYear($year);
}
```

**Impact:** Province with 20 dioceses × 5 churches each = 100+ queries

**Fix:** Use summary refresh once per year, then scope reads by affiliation joins

```php
public function refreshForScope(int $dioceseId, int $year): int
{
    // Compute annual summary once and use joins for scope filtering in reads
    return $standingModel->refreshForYear($year);
}
```

### 2.3 N+1 Query: Diocese Reporting Loop

**Location:** [Treasurer/Controllers/Reports.php](Treasurer/Controllers/Reports.php#L525-L540)

**Problem:**

```php
foreach ($dioceses as $diocese) {
    $churchIds = array_map(
        static fn($row) => (int) $row['id'],
        $db->table('churches')
            ->select('id')
            ->where('diocese_id', (int) $diocese['id'])
            ->get()
            ->getResultArray()  // Query per diocese!
    );
}
```

**Impact:** 50 dioceses = 50+ queries

**Fix:**

```php
$churchesByDiocese = $db->table('churches')
    ->select('id, diocese_id')
    ->whereIn('diocese_id', array_map(fn($d) => $d['id'], $dioceses))
    ->get()
    ->getResultArray();

$churchMap = [];
foreach ($churchesByDiocese as $church) {
    $dio = (int)$church['diocese_id'];
    if (!isset($churchMap[$dio])) {
        $churchMap[$dio] = [];
    }
    $churchMap[$dio][] = (int)$church['id'];
}

foreach ($dioceses as $diocese) {
    $churchIds = $churchMap[(int)$diocese['id']] ?? [];
}
```

---

## 3. High-Priority Issues (Fix Before Scale)

### 3.1 Missing Pagination on Large Result Sets

**Critical Endpoints:**

| Controller | Method | Current | Should Be | Est. Records |
|-----------|--------|---------|-----------|--------------|
| Pastor/Members | index | findAll() | paginate(25) | 500-5000 |
| Worship/Services | reports | findAll() | paginate(50) | 100-1000/year |
| Treasurer/Donations | index | findAll() | paginate(50) | 1000-10000 |
| Treasurer/Expenses | index | findAll() | paginate(50) | 100-1000 |
| Volunteer/Volunteers | index | findAll() | paginate(25) | 50-500 |
| Province/Dashboard | reporting_aggregation | getAll + loop | paginate(100) | 100-1000/scope |

**Implementation Template:**

```php
public function index()
{
    $perPage = (int) ($this->request->getGet('per_page') ?? 25);
    $page = (int) ($this->request->getGet('page') ?? 1);
    
    $builder = $this->model->where('church_id', $churchId);
    
    // Apply filters...
    if ($search) {
        $builder->like('name', $search);
    }
    
    // Paginate
    $data = [
        'records' => $builder->paginate($perPage),
        'pager' => $this->model->pager,
        'total' => $builder->countAllResults(),
    ];
}
```

### 3.2 Missing Indexes

**Recommended Indexes (Estimated Priority):**

```sql
-- 1. Member church affiliations (CRITICAL - used in all member queries)
ALTER TABLE member_church_affiliations 
  ADD INDEX idx_church_active (church_id, active),
  ADD INDEX idx_user_church_active (user_id, church_id, active);

-- 2. Attendance queries (HIGH - used in reports & dashboards)
ALTER TABLE attendance
  ADD INDEX idx_service_user (service_id, user_id),
  ADD INDEX idx_user_attended (user_id, attended);

-- 3. Donations & Expenses (HIGH - used in financial queries)
ALTER TABLE donations
  ADD INDEX idx_church_date (church_id, date),
  ADD INDEX idx_date_range (date, church_id);

ALTER TABLE expenses
  ADD INDEX idx_church_date (church_id, date),
  ADD INDEX idx_date_range (date, church_id);

-- 4. Services (MEDIUM - used in attendance reports)
ALTER TABLE services
  ADD INDEX idx_church_date (church_id, date),
  ADD INDEX idx_date_range (date, church_id);

-- 5. Member Standing (MEDIUM - used in standing queries)
ALTER TABLE member_standing_summary
    ADD INDEX idx_member_standing_summary_year_status (year, is_good_standing),
    ADD INDEX idx_member_standing_summary_user_year (user_id, year);
```

---

## 4. Medium-Priority Issues

### 4.1 Inconsistent Eager Loading

**Current Pattern (Mixed):**

```php
// Some places: eager load correctly
$donations = $this->buildFilteredDonationsQuery()
    ->join('profile', ...)
    ->join('funds', ...)
    ->paginate(50);

// Other places: fetch separately
$members = $this->memberModel->findAll();  // N queries if accessing profile per member
```

**Standardize Import Pattern:**

```php
// Use model relationships or consistent join patterns
public function getMembersWithFamilies($churchId)
{
    // Option 1: Eager load via joins
    return $db->table('profile p')
        ->select('p.*, f.family_name, fm.relationship')
        ->join('family_members fm', 'fm.user_id = p.user_id', 'left')
        ->join('families f', 'f.id = fm.family_id', 'left')
        ->where('p.church_id', $churchId)
        ->get()
        ->getResultArray();
    
    // Then map in memory
    $result = [];
    foreach ($rows as $row) {
        $userId = $row['user_id'];
        if (!isset($result[$userId])) {
            $result[$userId] = [
                'profile' => $row,
                'families' => []
            ];
        }
        if ($row['family_id']) {
            $result[$userId]['families'][] = $row;
        }
    }
    return array_values($result);
}
```

### 4.2 Caching High-Access Data

**Candidates for Caching (1-hour TTL):**

```php
// 1. Chart of Accounts (queried in many financial endpoints)
public function getActiveByType(?string $type = null): array
{
    $cacheKey = 'chart_accounts_' . ($type ?? 'all');
    
    if ($cached = cache($cacheKey)) {
        return $cached;
    }
    
    $builder = $this->where('is_active', 1);
    if ($type !== null) {
        $builder->where('account_type', $type);
    }
    
    $result = $builder->orderBy('sort_order', 'ASC')
        ->orderBy('code', 'ASC')
        ->findAll();
    
    cache()->save($cacheKey, $result, 3600); // 1 hour
    return $result;
}

// Add cache invalidation on update/create
public function update($id = null, $data = null)
{
    $result = parent::update($id, $data);
    cache()->delete('chart_accounts_all');
    cache()->delete('chart_accounts_income');
    cache()->delete('chart_accounts_expense');
    return $result;
}

// 2. Roles/Permissions (queried on every dashboard load)
public function getCreatableRoles(): array
{
    $userId = auth()->id();
    $cacheKey = 'user_creatable_roles_' . $userId;
    
    if ($cached = cache($cacheKey)) {
        return $cached;
    }
    
    // Determine roles (existing logic)
    $roles = []; // ... populate
    
    cache()->save($cacheKey, $roles, 3600);
    return $roles;
}

// Invalidate on role change
public function assignUserToRole($userId, $role)
{
    cache()->delete('user_creatable_roles_' . $userId);
    // ... perform assignment
}

// 3. Church List for Super Admin (accessed frequently)
public function getAllChurchesBrief(): array
{
    if ($cached = cache('all_churches_brief')) {
        return $cached;
    }
    
    $churches = $db->table('churches c')
        ->select('c.id, c.name')
        ->join('dioceses d', 'd.id = c.diocese_id', 'left')
        ->orderBy('d.name, c.name')
        ->get()
        ->getResultArray();
    
    cache()->save('all_churches_brief', $churches, 3600);
    return $churches;
}

// Invalidate on church create/update
public function invalidateChurchCache()
{
    cache()->delete('all_churches_brief');
}
```

---

## 5. Query Analysis Tools & Debugging

### 5.1 Enable MySQL Slow Query Log

**On Development/Production:**

```bash
# Add to my.cnf or my.ini
[mysqld]
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow-query.log
long_query_time = 0.5  # 500ms threshold (adjust per environment)
```

**Monitor:**

```bash
# Review slow queries
tail -f /var/log/mysql/slow-query.log

# Analyze with mysqldumpslow
mysqldumpslow /var/log/mysql/slow-query.log | head -20
```

### 5.2 Query Analysis in CodeIgniter

**Use EXPLAIN in Controllers:**

```php
// During development, inspect query plans
public function debugAttendanceQuery($serviceId)
{
    $builder = $this->attendanceModel
        ->select('a.*, u.username')
        ->join('users u', 'u.id = a.user_id')
        ->where('service_id', $serviceId);
    
    // Get query plan
    $table = $builder->getCompiledSelect();
    $explain = $this->db->query('EXPLAIN ' . $table)->getResultArray();
    
    log_message('info', 'Attendance Query Plan: ' . json_encode($explain));
    
    return $builder->get()->getResultArray();
}
```

### 5.3 Benchmark Critical Paths

**Create Benchmarks:**

```php
// tests/unit/PerformanceTest.php
public function testMemberListLoadTime()
{
    $start = microtime(true);
    
    $members = model('ProfileModel')->getChurchProfiles(1); // 500 members
    
    $elapsed = microtime(true) - $start;
    $this->assertLessThan(0.5, $elapsed, 'Member list should load in <500ms');
}

public function testDonationReportGenerationTime()
{
    $start = microtime(true);
    
    $report = model('DonationModel')->getDonationReport(1, '2025-01-01', '2025-12-31');
    
    $elapsed = microtime(true) - $start;
    $this->assertLessThan(2.0, $elapsed, 'Report should generate in <2 seconds');
}
```

---

## 6. Implementation Timeline

### Phase 1: Critical N+1 Fixes (Day 1-2)

- [ ] Fix member transfer N+1 (Minutes: ~30)
- [ ] Fix standing refresh N+1 (Minutes: ~45)
- [ ] Fix diocese reporting loop (Minutes: ~20)
- [ ] Test and validate with data migration scripts (Minutes: ~60)

### Phase 2: Pagination Implementation (Day 2-3)

- [ ] Add pagination to Pastor/Members (Minutes: ~30)
- [ ] Add pagination to Worship/Services (Minutes: ~25)
- [ ] Add pagination to Treasurer/Donations (Minutes: ~25)
- [ ] Add pagination to Treasurer/Expenses (Minutes: ~25)
- [ ] Update front-end paging controls (Minutes: ~60)

### Phase 3: Database Indexes (Day 1)

- [ ] Create migration file for all indexes (Minutes: ~20)
- [ ] Test index impact with EXPLAIN (Minutes: ~30)
- [ ] Document index rationale (Minutes: ~15)

### Phase 4: Caching Layer (Day 3-4)

- [ ] Implement Chart of Accounts caching (Minutes: ~30)
- [ ] Implement role/permissions caching (Minutes: ~40)
- [ ] Add cache invalidation hooks (Minutes: ~30)
- [ ] Test cache hit rates (Minutes: ~45)

### Phase 5: Query Analysis & Monitoring (Day 4)

- [ ] Set up slow query logging (Minutes: ~20)
- [ ] Create performance benchmarks (Minutes: ~45)
- [ ] Document findings in performance report (Minutes: ~30)

**Total Estimated Effort:** 12-15 hours

---

## 7. Validation Checklist

```
CRITICAL FIXES
☐ Member transfer operation <100ms for 100 members
☐ Standing refresh <1s for 100 churches
☐ Diocese reporting <500ms per diocese
☐ No queries in loops in hot paths

PAGINATION
☐ Large lists paginate at 25-50 rows per page
☐ Pager controls visible in views
☐ URL params preserved in pagination links
☐ Filter state maintained on page navigation

INDEXES
☐ All indexes created successfully
☐ EXPLAIN shows index usage (key != NULL)
☐ No table scans on indexed columns
☐ Query time improved >20%

CACHING
☐ Cache hits recorded >80% for Chart of Accounts
☐ Cache invalidation works on edit/create
☐ Cache miss doesn't cause cascading queries
☐ TTL set to 1 hour; manual invalidation on sensitive updates

MONITORING
☐ Slow query log enabled and monitored
☐ Benchmarks run and documented
☐ Performance baseline established
☐ Dashboard load time <2s
```

---

## 8. Performance Targets

| Metric | Current | Target | Threshold |
|--------|---------|--------|-----------|
| Dashboard load time | ~2-3s | <1.5s | Critical if >2.5s |
| Member list (500 records) | ~800ms | <300ms | Warning if >500ms |
| Donation export (10k records) | ~5s | <2s | Warning if >3s |
| Attendance report generation | ~3s | <1.5s | Warning if >2s |
| Report export PDF generation | ~4s | <2s | Warning if >3s |

---

## 9. Post-Implementation Review

**Schedule:** 2 weeks after deployment

- [ ] Monitor slow query log for new bottlenecks
- [ ] Compare actual vs. target performance
- [ ] Adjust index strategy if needed
- [ ] Review cache hit rates
- [ ] Identify any missed N+1 patterns
- [ ] Document lessons learned

---

## Related Documents

- [OPTIMIZATION_SUMMARY.md](OPTIMIZATION_SUMMARY.md) - Previous optimizations applied
- [DEEP_ANALYSIS_REPORT_2026_03_31.md](DEEP_ANALYSIS_REPORT_2026_03_31.md#2.7) - Original recommendation (Section 2.7)
- Database Schema: See [.github/copilot-instructions.md](.github/copilot-instructions.md)

---

**Next Steps:** Proceed to Phase 1 implementation of critical N+1 fixes.
