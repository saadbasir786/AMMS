import assert from 'node:assert/strict'
import { after, before, test } from 'node:test'
import path from 'node:path'
import React from 'react'
import { renderToStaticMarkup } from 'react-dom/server'
import { createServer } from 'vite'
import react from '@vitejs/plugin-react'

let server
let CaseCard, FormCard, ParticipantCard, contracts
before(async () => {
  server = await createServer({
    configFile: false, plugins: [react()],
    resolve: { alias: { '@': path.resolve('src') } },
    server: { middlewareMode: true, hmr: false }, appType: 'custom',
  })
  CaseCard = (await server.ssrLoadModule('/src/features/meetings/components/meeting-exception-case-card.tsx')).MeetingExceptionCaseCard
  FormCard = (await server.ssrLoadModule('/src/features/meetings/components/meeting-exception-form-card.tsx')).MeetingExceptionFormCard
  ParticipantCard = (await server.ssrLoadModule('/src/features/meetings/components/participant-change-case-card.tsx')).ParticipantChangeCaseCard
  contracts = await server.ssrLoadModule('/src/features/meetings/lib/workspace-contracts.ts')
})
after(async () => { await server?.close() })

const base = { id: 1, case_type: 'POSTPONEMENT', status: 'UNDER_REVIEW', governance_mode: 'PEER_MULTI_UNIT', created_at: '2026-09-07T10:00:00Z', reviews: [] }
const renderCase = (item) => renderToStaticMarkup(React.createElement(CaseCard, { item, busy: false, onAction() {}, async onReviewAction() {} }))

test('only the authorized review row exposes approve and correction controls', () => {
  const html = renderCase({ ...base, reviews: [
    { id: 10, reviewer_type: 'UNIT', review_requirement: 'APPROVAL', status: 'PENDING', unit_name: 'ریاست مجاز', action_capabilities: { approve: true, correction: true } },
    { id: 11, reviewer_type: 'UNIT', review_requirement: 'APPROVAL', status: 'PENDING', unit_name: 'ریاست دیگر', action_capabilities: { approve: false, correction: false } },
  ] })
  assert.equal((html.match(/<button/g) || []).length, 2)
  assert.match(html, /برگشت جهت اصلاح/)
  assert.doesNotMatch(html, /PEER_MULTI_UNIT|APPROVAL/)
})

test('returned exceptions expose edit before submission, and resumption exposes its schedule action', () => {
  const returned = renderCase({ ...base, status: 'RETURNED_FOR_CORRECTION', action_capabilities: { edit: true, submit: false } })
  assert.match(returned, /ویرایش و اصلاح دلیل/)
  assert.doesNotMatch(returned, /ارسال پرونده برای بررسی/)
  const resume = renderCase({ ...base, case_type: 'RESUMPTION', status: 'READY_TO_APPLY', action_capabilities: { resume: true, apply: false } })
  assert.match(resume, /تعیین زمان جدید و فعال‌سازی جلسه/)
  assert.doesNotMatch(resume, /اجرا و اعمال تصمیم نهایی/)
})

test('postponed meeting form offers resumption and its payload satisfies the API contract', () => {
  const html = renderToStaticMarkup(React.createElement(FormCard, { busy: false, canCreate: true, meetingStatus: 'POSTPONED', async onCreateCase() { return true } }))
  assert.match(html, /value="RESUMPTION" selected=""/)
  assert.doesNotMatch(html, /value="REOPENING"/)
  assert.equal(contracts.exceptionCreatePayload('RESUMPTION', 'دلیل رسمی').replacement_expected, true)
  assert.equal(contracts.exceptionCreatePayload('POSTPONEMENT', 'دلیل رسمی').replacement_expected, false)
})

test('no active participant case is never stale; changed schedule is stale', () => {
  assert.equal(contracts.isParticipantBaselineStale(null, 3), false)
  assert.equal(contracts.isParticipantBaselineStale(undefined, 3), false)
  assert.equal(contracts.isParticipantBaselineStale({ baseline_schedule_version_id: 3 }, 3), false)
  assert.equal(contracts.isParticipantBaselineStale({ baseline_schedule_version_id: 2 }, 3), true)
})

test('participant delta names use the snake_case API relationships', () => {
  const html = renderToStaticMarkup(React.createElement(ParticipantCard, {
    activeCase: { id: 1, status: 'DRAFT', owner_type: 'UNIT', owner_unit: { name: 'ریاست نمونه' }, items: [{ id: 1, operation: 'REPLACE', old_employee: { full_name: 'کارمند قبلی' }, new_employee: { full_name: 'کارمند جدید' } }] },
    canEdit: false, busy: false, async onDeleteItem() {},
  }))
  assert.match(html, /کارمند قبلی/)
  assert.match(html, /کارمند جدید/)
  assert.match(html, /ریاست نمونه/)
  assert.doesNotMatch(html, /DRAFT/)
})
