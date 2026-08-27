export type OrganizationalUnit = {
  id: number
  name: string
  code?: string
  parent_id?: number | null
  unit_type?: string
  status?: string
}

export type EmployeeItem = {
  id: number
  full_name: string
  employee_number?: string
  organizational_unit_id?: number
  unit?: { id?: number; name?: string } | null
  position?: { title?: string } | null
}
