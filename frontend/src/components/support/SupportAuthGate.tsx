"use client";

import { usePathname, useRouter } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import { ApiError, getSupportSession } from "@/lib/api";
import styles from "./SupportAuthGate.module.css";

type GateState = "checking" | "authenticated" | "unavailable";

export function SupportAuthGate({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [state, setState] = useState<GateState>("checking");
  const [retry, setRetry] = useState(0);

  useEffect(() => {
    let active = true;

    getSupportSession()
      .then(() => {
        if (active) setState("authenticated");
      })
      .catch((error: unknown) => {
        if (!active) return;

        if (error instanceof ApiError && error.isUnauthorized) {
          const next = safeSupportPath(pathname);
          router.replace(`/support/login?next=${encodeURIComponent(next)}`);
          return;
        }

        setState("unavailable");
      });

    return () => {
      active = false;
    };
  }, [pathname, retry, router]);

  if (state === "authenticated") return children;

  return (
    <main className={styles.state} aria-busy={state === "checking"}>
      {state === "checking" ? (
        <p role="status">Checking support access…</p>
      ) : (
        <div>
          <p role="alert">We could not verify support access. Please try again.</p>
          <button type="button" onClick={() => setRetry((current) => current + 1)}>Try again</button>
        </div>
      )}
    </main>
  );
}

function safeSupportPath(pathname: string): string {
  if (pathname === "/support" || /^\/support\/refunds\/\d+$/.test(pathname)) return pathname;

  return "/support";
}
