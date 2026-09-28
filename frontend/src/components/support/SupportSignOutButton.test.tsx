import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { logoutSupport } from "@/lib/api";
import { SupportSignOutButton } from "./SupportSignOutButton";

const mockReplace = jest.fn();

jest.mock("next/navigation", () => ({
  useRouter: () => ({ replace: mockReplace }),
}));

jest.mock("@/lib/api", () => ({ logoutSupport: jest.fn() }));

const logoutSupportMock = jest.mocked(logoutSupport);

describe("SupportSignOutButton", () => {
  beforeEach(() => jest.clearAllMocks());

  it("calls server logout and redirects to login", async () => {
    logoutSupportMock.mockResolvedValue({ authenticated: false });
    render(<SupportSignOutButton />);

    fireEvent.click(screen.getByRole("button", { name: "Sign out" }));

    await waitFor(() => expect(logoutSupportMock).toHaveBeenCalled());
    expect(mockReplace).toHaveBeenCalledWith("/support/login");
  });
});
