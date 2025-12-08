import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome to Artemis" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 text-[#1b1b18] dark:bg-[#2D2D2D]">
                {/* Header Navigation */}
                <header className="absolute top-0 left-0 right-0 p-6">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-md bg-[#00D9A5] px-5 py-2 text-sm font-medium text-white hover:bg-[#00C496] transition-colors"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-md px-5 py-2 text-sm font-medium text-[#1b1b18] hover:text-[#00D9A5] transition-colors dark:text-[#EDEDEC] dark:hover:text-[#00D9A5]"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-block rounded-md bg-[#00D9A5] px-5 py-2 text-sm font-medium text-white hover:bg-[#00C496] transition-colors"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                {/* Main Content */}
                <main className="flex flex-col items-center text-center">
                    {/* Logo */}
                    <div className="mb-8">
                        <img 
                            src="/img/logo/artemis.png" 
                            alt="Artemis Logo" 
                            className="w-70 h-70 object-contain"
                        />
                    </div>

                    {/* Title */}
                    <h1 className="text-5xl font-bold mb-4 dark:text-white">
                        <span className="text-[#00D9A5]">ARTEM</span>
                        <span className="text-[#A0A0A0]">I</span>
                        <span className="text-[#00D9A5]">S</span>
                    </h1>

                    {/* Subtitle */}
                    <p className="text-lg text-[#706f6c] dark:text-[#A0A0A0] mb-2">
                        Advanced RTS Tracking & E-commerce
                    </p>
                    <p className="text-lg text-[#706f6c] dark:text-[#A0A0A0] mb-8">
                        Management Integrated System
                    </p>

                    {/* CTA Buttons */}
                    {!auth.user && (
                        <div className="flex gap-4">
                            <Link
                                href={register()}
                                className="inline-block rounded-md bg-[#00D9A5] px-8 py-3 text-base font-medium text-white hover:bg-[#00C496] transition-colors"
                            >
                                Get Started
                            </Link>
                            <Link
                                href={login()}
                                className="inline-block rounded-md border border-[#A0A0A0] px-8 py-3 text-base font-medium text-[#1b1b18] hover:border-[#00D9A5] hover:text-[#00D9A5] transition-colors dark:text-[#EDEDEC] dark:border-[#505050] dark:hover:border-[#00D9A5]"
                            >
                                Sign In
                            </Link>
                        </div>
                    )}

                    {auth.user && (
                        <Link
                            href={dashboard()}
                            className="inline-block rounded-md bg-[#00D9A5] px-8 py-3 text-base font-medium text-white hover:bg-[#00C496] transition-colors"
                        >
                            Go to Dashboard
                        </Link>
                    )}
                </main>

                {/* Footer */}
                <footer className="absolute bottom-0 left-0 right-0 p-6 text-center">
                    <p className="text-sm text-[#A0A0A0]">
                        © {new Date().getFullYear()} Artemis. All rights reserved.
                    </p>
                </footer>
            </div>
        </>
    );
}
