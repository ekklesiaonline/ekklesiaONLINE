# Observability Policy

Date: 2026-04-02
Status: Phase B baseline policy

## Objective

Provide a minimum production-ready observability standard for request tracing, audit events, and slow-query diagnostics in ekklesiaONLINE.

## Signals

1. Request monitoring

- Source: request monitoring filter
- Captures: method, route, status, latency, lightweight context

1. Audit events

- Source: audit service
- Captures: actor, action, entity, change summary, parish scope, request metadata

1. Slow-query monitoring

- Source: monitoring service slow-query hook
- Captures: duration, threshold, query hash, sanitized SQL preview

## Redaction and Privacy

The monitoring pipeline must redact sensitive key classes before logging:

- credentials and secrets
- personal contact data
- financial values
- freeform notes

Sensitive payloads are never logged in plaintext.

## Environment Controls

The following environment switches govern observability behavior:

- monitoring.enabled
- monitoring.fileLogEnabled
- monitoring.slowQueryMs
- monitoring.auditDbEnabled
- monitoring.auditLogEnabled

Default operational baseline:

- monitoring.enabled: true
- monitoring.fileLogEnabled: true
- monitoring.slowQueryMs: 100
- monitoring.auditDbEnabled: true
- monitoring.auditLogEnabled: true

## Ownership

- Application team: event schema, redaction rules, audit semantics
- Platform/operations team: log retention, storage, alert routing, incident process

## Retention Baseline

- Application logs: 30 days online minimum
- Audit logs: 90 days online minimum (or per compliance policy)
- Backups for observability stores follow platform retention policy

## Incident and Alerting Minimum

Trigger alerts for:

- repeated 5xx spikes
- sustained 429 spikes on critical write endpoints
- audit write failures
- slow-query threshold regressions

## Implementation Notes (Phase B)

This policy is intentionally vendor-neutral. External sink selection (e.g., ELK/Sentry/cloud logging) is deployment-specific, but all deployments must preserve the same redaction and event-shape guarantees.
