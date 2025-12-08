export function addIf(params: Record<string, string>, key: string, value?: string) {
    if (!value) return
    const v = value.toString().trim()
    if (v === '') return
    if (v.toLowerCase() === 'all') return
    params[key] = v
}

export default addIf
