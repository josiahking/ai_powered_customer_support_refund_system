import Link from "next/link";
import { SupportLoginForm } from "@/components/support/SupportLoginForm";
import { AppHeader } from "@/components/shared/AppHeader";
import styles from "./page.module.css";

export default function SupportLoginPage() {
  return (
    <div className={styles.page}>
      <AppHeader active="customer" />
      <main className={styles.main}>
        <section className={styles.panel} aria-labelledby="support-login-heading">
          <p className={styles.eyebrow}>WORKSPACE / REFUND OPERATIONS</p>
          <h1 id="support-login-heading">Support sign in</h1>
          <p className={styles.description}>Sign in with your support credentials to review refund requests and audit details.</p>
          <SupportLoginForm />
          <Link className={styles.backLink} href="/">Return to customer refund flow</Link>
        </section>
      </main>
    </div>
  );
}
