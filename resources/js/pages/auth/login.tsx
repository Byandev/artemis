import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head, usePage } from '@inertiajs/react';
import { Info, LoaderCircle } from 'lucide-react';
import { WorkspaceInvitation } from '@/types/models/WorkspaceInvitation';



interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    invitation?: WorkspaceInvitation | null;
    invitationToken?: string | null;
    [key: string]: unknown;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { invitation, invitationToken } = usePage<LoginProps>().props;

    return (
        <AuthLayout
            title="Log in to your account"
            description={invitation ? `Login to join ${invitation.workspace.name}` : "Enter your email and password below to log in"}
        >
            <Head title="Log in" />

            {invitation && (
                <Alert className="mb-4">
                    <Info className="h-4 w-4" />
                    <AlertDescription>
                        You've been invited to join <strong>{invitation.workspace.name}</strong>
                        Login to accept the invitation.
                    </AlertDescription>
                </Alert>
            )}

            <Form
                {...AuthenticatedSessionController.store()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        {invitationToken && (
                            <input type="hidden" name="invitation" value={invitationToken} />
                        )}

                        <div className="grid gap-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="email" className="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    defaultValue={invitation?.email || ''}
                                    readOnly={!!invitation}
                                    className="h-10 text-[14px]"
                                />
                                {invitation && (
                                    <p className="text-[12px] text-gray-400 dark:text-gray-500">
                                        This email matches your invitation.
                                    </p>
                                )}
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-1.5">
                                <div className="flex items-center">
                                    <Label htmlFor="password" className="text-[13px] font-medium text-gray-700 dark:text-gray-300">
                                        Password
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-[12px]"
                                            tabIndex={5}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                    className="h-10 text-[14px]"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-2.5">
                                <Checkbox id="remember" name="remember" tabIndex={3} />
                                <Label htmlFor="remember" className="text-[13px] text-gray-600 dark:text-gray-400 font-normal">
                                    Remember me
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 h-10 w-full text-[14px] font-medium"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Log in
                            </Button>
                        </div>

                        <p className="text-center text-[13px] text-gray-500 dark:text-gray-400">
                            Don't have an account?{' '}
                            <TextLink href={invitationToken ? register({ query: { invitation: invitationToken } }).url : register().url} tabIndex={5}>
                                Sign up
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
