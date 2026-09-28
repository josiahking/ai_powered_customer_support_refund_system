import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { ApiError, loginSupport } from "@/lib/api";
import { SupportLoginForm } from "./SupportLoginForm";

const mockReplace = jest.fn();

jest.mock("next/navigation", () => ({
  useRouter: () => ({ replace: mockReplace }),
}));

jest.mock("@/lib/api", () => {
  const actual = jest.requireActual<typeof import("@/lib/api")>("@/lib/api");
  return { ...actual, loginSupport: jest.fn() };
});

const loginSupportMock = jest.mocked(loginSupport);

describe("SupportLoginForm", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.history.replaceState({}, "", "/support/login");
  });

  it("renders username and a password input without persisting the password", () => {
    render(<SupportLoginForm />);

    expect(screen.getByLabelText("Username")).toBeInTheDocument();
    expect(screen.getByLabelText("Password")).toHaveAttribute("type", "password");
    expect(screen.getByLabelText("Password")).toHaveAttribute("autocomplete", "off");
  });

  it("shows a useful invalid-credentials message", async () => {
    loginSupportMock.mockRejectedValue(new ApiError("Service request failed.", 401));
    render(<SupportLoginForm />);

    fireEvent.change(screen.getByLabelText("Username"), { target: { value: "support" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "wrong" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/invalid username or password/i);
    expect(screen.getByLabelText("Password")).toHaveValue("");
  });

  it("redirects to the requested internal support page after login", async () => {
    loginSupportMock.mockResolvedValue({ authenticated: true });
    window.history.replaceState({}, "", "/support/login?next=%2Fsupport%2Frefunds%2F31");
    render(<SupportLoginForm />);

    fireEvent.change(screen.getByLabelText("Username"), { target: { value: "support" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "test-only" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(loginSupportMock).toHaveBeenCalledWith({ username: "support", password: "test-only" });
    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith("/support/refunds/31"));
  });

  it("rejects external next destinations", async () => {
    loginSupportMock.mockResolvedValue({ authenticated: true });
    window.history.replaceState({}, "", "/support/login?next=https%3A%2F%2Fevil.example");
    render(<SupportLoginForm />);

    fireEvent.change(screen.getByLabelText("Username"), { target: { value: "support" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "test-only" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith("/support"));
  });

  it("shows an unavailable-service message for missing server configuration", async () => {
    loginSupportMock.mockRejectedValue(new ApiError("Service request failed.", 503));
    render(<SupportLoginForm />);

    fireEvent.change(screen.getByLabelText("Username"), { target: { value: "support" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "attempt" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByRole("alert")).toHaveTextContent(/sign-in is unavailable/i);
  });
});

