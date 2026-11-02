import { createContext } from "react";

const GlobalContext = createContext<AppContext>({
    showRemoteModal: false,
    setShowRemoteModal: () => {},
})

export default GlobalContext;