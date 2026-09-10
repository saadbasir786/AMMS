import { useEffect, useState } from 'react'
import { api, apiData } from '@/lib/api-client'

export type AmmsMetadata = {
  organizational_unit_types: string[]
  formal_governance_unit_types: string[]
  position_types: string[]
  roles: string[]
  system_roles?: string[]
  custom_roles?: { name: string; label: string }[]
  permissions?: AmmsPermissionMetadata[]
  special_capabilities: string[]
  labels: {
    organizational_unit_types: Record<string, string>
    position_types: Record<string, string>
    roles: Record<string, string>
  }
}

export type AmmsPermissionMetadata = {
  name: string
  category: string
  custom_role_assignable: boolean
  protected: boolean
  delegatable: boolean
}

export const fallbackAmmsMetadata: AmmsMetadata = {
  organizational_unit_types: [
    'GENERAL_PRESIDENCY', 'DEPUTY_PRESIDENCY', 'GENERAL_DIRECTORATE', 'DIRECTORATE',
    'ATTACHED_AGENCY', 'DEPUTY_DIRECTORATE', 'AMIRIATE', 'GENERAL_MANAGEMENT',
    'MANAGEMENT', 'SECTION', 'OTHER', 'DEPARTMENT', 'OFFICE', 'HIGH_AUTHORITY',
    'AUTHORITY_OFFICE',
  ],
  formal_governance_unit_types: [
    'GENERAL_PRESIDENCY', 'DEPUTY_PRESIDENCY', 'GENERAL_DIRECTORATE', 'DIRECTORATE',
    'ATTACHED_AGENCY', 'DEPUTY_DIRECTORATE',
  ],
  position_types: [
    'DIRECTOR', 'DEPUTY_DIRECTOR', 'AMIR', 'GENERAL_MANAGER', 'EXPERT', 'MANAGER',
    'EMPLOYEE', 'EXECUTIVE_MANAGEMENT', 'HIGH_AUTHORITY', 'AUTHORITY_OFFICE',
    'EXECUTIVE_SECRETARY', 'OTHER',
  ],
  roles: [
    'SYSTEM_ADMIN', 'EMPLOYEE', 'DIRECTOR', 'DEPUTY_DIRECTOR', 'EXECUTIVE_MANAGEMENT',
    'AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY', 'HIGH_AUTHORITY',
  ],
  permissions: [],
  special_capabilities: [
    'BIOMETRIC_ENROLLMENT', 'INTERNAL_MEETING_REQUEST_APPROVAL',
    'DIRECTORATE_MINUTES_APPROVAL',
  ],
  labels: {
    organizational_unit_types: {
      GENERAL_PRESIDENCY: 'ریاست عمومی', DEPUTY_PRESIDENCY: 'معاونیت ریاست عمومی',
      GENERAL_DIRECTORATE: 'ریاست عمومی', DIRECTORATE: 'ریاست', ATTACHED_AGENCY: 'ادارهٔ تابعه',
      DEPUTY_DIRECTORATE: 'معاونیت ریاست', AMIRIATE: 'آمریت', GENERAL_MANAGEMENT: 'مدیریت عمومی',
      MANAGEMENT: 'مدیریت', SECTION: 'شعبه', OTHER: 'سایر واحدها', DEPARTMENT: 'دیپارتمنت',
      OFFICE: 'دفتر', HIGH_AUTHORITY: 'مقام عالی', AUTHORITY_OFFICE: 'دفتر مقام',
    },
    position_types: {
      DIRECTOR: 'رئیس', DEPUTY_DIRECTOR: 'معاون ریاست', AMIR: 'آمر', GENERAL_MANAGER: 'مدیر عمومی',
      EXPERT: 'کارشناس', MANAGER: 'مدیر', EMPLOYEE: 'کارمند', EXECUTIVE_MANAGEMENT: 'مدیریت اجرائیه',
      HIGH_AUTHORITY: 'مقام عالی', AUTHORITY_OFFICE: 'دفتر مقام', EXECUTIVE_SECRETARY: 'منشی اجرائیه',
      OTHER: 'سایر بست‌ها',
    },
    roles: {
      SYSTEM_ADMIN: 'مدیر سیستم (فنی)', EMPLOYEE: 'کارمند', DIRECTOR: 'رئیس اداره',
      DEPUTY_DIRECTOR: 'معاون اداره', EXECUTIVE_MANAGEMENT: 'مدیریت اجرائیه',
      AUTHORITY_OFFICE: 'دفتر مقام عالی', EXECUTIVE_SECRETARY: 'منشی مقام عالی', HIGH_AUTHORITY: 'مقام عالی',
    },
  },
}

let cachedMetadata: AmmsMetadata | null = null
let metadataRequest: Promise<AmmsMetadata> | null = null

async function loadAmmsMetadata(): Promise<AmmsMetadata> {
  if (cachedMetadata) return cachedMetadata
  if (!metadataRequest) {
    metadataRequest = api.get('/meta/amms')
      .then((response) => {
        const data = apiData(response) as Partial<AmmsMetadata>
        cachedMetadata = {
          ...fallbackAmmsMetadata,
          ...data,
          labels: {
            ...fallbackAmmsMetadata.labels,
            ...(data.labels || {}),
          },
        }
        return cachedMetadata
      })
      .catch(() => fallbackAmmsMetadata)
      .finally(() => {
        metadataRequest = null
      })
  }
  return metadataRequest
}

export function useAmmsMetadata(): AmmsMetadata {
  const [metadata, setMetadata] = useState<AmmsMetadata>(cachedMetadata || fallbackAmmsMetadata)

  useEffect(() => {
    let mounted = true
    void loadAmmsMetadata().then((loaded) => {
      if (mounted) setMetadata(loaded)
    })
    return () => {
      mounted = false
    }
  }, [])

  return metadata
}
