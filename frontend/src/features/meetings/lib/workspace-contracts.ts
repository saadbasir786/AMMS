import type { ExceptionCaseType, ParticipantChangeCase } from '../types'

export function exceptionCreatePayload(caseType: ExceptionCaseType, reason: string) {
  return { case_type: caseType, reason, replacement_expected: caseType === 'RESUMPTION' }
}

export function isParticipantBaselineStale(active: ParticipantChangeCase | null | undefined, currentScheduleId: number | undefined, currentParticipantId?: number | undefined) {
  return Boolean(active && (
    Number(active.baseline_schedule_version_id) !== Number(currentScheduleId)
    || (currentParticipantId !== undefined && Number(active.baseline_participant_version_id) !== Number(currentParticipantId))
  ))
}
