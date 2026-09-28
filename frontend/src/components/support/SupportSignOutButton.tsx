"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { logoutSupport } from "@/lib/api";
import styles from "@/components/shared/AppHeader.module.css";

export function SupportSignOutButton() {
  const router = useRouter();
  const [isSigningOut, setIsSigningOut] = useState(false);
  const [error, setError] = useState("");

  async function signOut() {
    setIsSigningOut(true);
    setError("");

    try {
      await logoutSupport();
      router.replace("/support/login");
    } catch {
      setError("We could not sign you out. Please try again.");
      setIsSigningOut(false);
    }
  }

  return (
    <>
      <button className={styles.signOut} disabled={isSigningOut} onClick={signOut} type="button">
        {isSigningOut ? "Signing out…" : "Sign out"}
      </button>
      {error && <span className={styles.signOutError} role="alert">{error}</span>}
    </>
  );
}
