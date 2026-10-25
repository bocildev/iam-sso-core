/**
 * IAM-SSO-CORE Client Composable Reference (Nuxt 3 / TypeScript)
 *
 * Cara pakai di aplikasi klien Nuxt (mis. portofolio-motion):
 * Salin file ini ke `composables/useIamSso.ts`, lalu set runtimeConfig.public:
 *   iamIssuer: 'http://iam-sso-core.test:8888'
 *   iamClientId: 'app_...'
 *   iamRedirectUri: 'http://localhost:3000/auth/callback'
 */

export interface IamClaims {
  sub: string
  iss: string
  aud: string
  sid?: string
  tenant_id?: string | null
  roles: string[]
  permissions: string[]
  exp: number
}

function base64UrlEncode(buf: ArrayBuffer): string {
  const bytes = new Uint8Array(buf)
  let binary = ''
  for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i])
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

export async function createPkcePair(): Promise<{ verifier: string; challenge: string }> {
  const random = new Uint8Array(48)
  crypto.getRandomValues(random)
  const verifier = base64UrlEncode(random.buffer)
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))
  return { verifier, challenge: base64UrlEncode(digest) }
}

export function decodeJwtClaims(token: string): IamClaims {
  const parts = token.split('.')
  if (parts.length !== 3) throw new Error('Invalid JWT')
  const json = atob(parts[1].replace(/-/g, '+').replace(/_/g, '/'))
  return JSON.parse(json) as IamClaims
}

export function canPermission(claims: IamClaims | null, permission: string): boolean {
  if (!claims) return false
  for (const p of claims.permissions || []) {
    if (p === '*' || p === permission) return true
    if (p.endsWith('.*') && permission.startsWith(p.slice(0, -1))) return true
  }
  return false
}
