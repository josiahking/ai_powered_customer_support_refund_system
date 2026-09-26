import styles from "./page.module.css";

export default function Home() {
  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <span className={styles.brandMark} aria-hidden="true">W</span>
        <span>WORKNOON / PRODUCT CHALLENGE</span>
        <span className={styles.phase}>PHASE 0</span>
      </header>

      <main className={styles.main}>
        <p className={styles.eyebrow}>CUSTOMER SUPPORT SYSTEM</p>
        <h1>AI-Powered Customer Support Refund System</h1>
        <p className={styles.description}>
          The application foundation is ready for the next phase.
        </p>
        <div className={styles.status} role="status">
          <span className={styles.statusDot} />
          Frontend is running
        </div>
      </main>

      <footer className={styles.footer}>
        <span>WEB APPLICATION</span>
        <span>FOUNDATION ONLINE</span>
      </footer>
    </div>
  );
}
