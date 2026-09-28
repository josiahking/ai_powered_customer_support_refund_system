"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { ApiError, loginSupport } from "@/lib/api";
import styles from "./SupportLoginForm.module.css";

export function SupportLoginForm() {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState("");

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setIsSubmitting(true);

    try {
      await loginSupport({ username, password });
      setPassword("");
      router.replace(safeSupportDestination(new URLSearchParams(window.location.search).get("next")));
    } catch (requestError: unknown) {
      if (requestError instanceof ApiError && requestError.status === 401) {
        setError("Invalid username or password. Please try again.");
      } else if (requestError instanceof ApiError && requestError.status === 503) {
        setError("Support sign-in is unavailable. Contact your administrator.");
      } else {
        setError("We could not sign you in. Please try again.");
      }
      setPassword("");
      setIsSubmitting(false);
    }
  }

  return (
    <form autoComplete="off" className={styles.form} onSubmit={submit}>
      <label htmlFor="support-username">Username</label>
      <input
        autoComplete="off"
        id="support-username"
        name="username"
        onChange={(event) => setUsername(event.target.value)}
        required
        value={username}
      />

      <label htmlFor="support-password">Password</label>
      <input
        autoComplete="off"
        id="support-password"
        name="password"
        onChange={(event) => setPassword(event.target.value)}
        required
        type="password"
        value={password}
      />

      {error && <p className={styles.error} role="alert">{error}</p>}

      <button className={styles.submit} disabled={isSubmitting} type="submit">
        {isSubmitting ? "Signing in…" : "Sign in"}
      </button>
    </form>
  );
}

export function safeSupportDestination(value: string | null): string {
  if (value === "/support" || (value !== null && /^\/support\/refunds\/\d+$/.test(value))) {
    return value;
  }

  return "/support";
}
