import AsyncStorage from "@react-native-async-storage/async-storage";
import { Ionicons } from "@expo/vector-icons";
import { useEffect, useState } from "react";
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";

type PasswordCache = {
  username: string;
  password: string;
  savedAt: number;
  token?: string;
};

type UserRole = "admin" | "user";

type AppUser = {
  username: string;
  role: UserRole;
  institution: string;
};

type DashboardTab = "articles" | "users" | "institutions" | "profile";

type FeaturedArticle = {
  id: number;
  title: string;
  audience: string;
  snippet: string;
  content: string;
  institutionIds: number[];
  targetInstitutions: string[];
  adminOnly?: boolean;
};

type RemoteArticle = {
  id: number;
  title: string;
  content: string;
  snippet?: string;
  institution_names?: string;
  institution_ids?: number[];
};

type RemoteInstitution = {
  id: number;
  name: string;
};

type InstitutionItem = {
  id: number;
  name: string;
};

type QuickAction = {
  id: DashboardTab;
  label: string;
  icon: "document-text-outline" | "people-outline" | "business-outline" | "person-circle-outline";
  roles: UserRole[];
};

const SESSION_WINDOW_MS = 48 * 60 * 60 * 1000;
const USERNAME_STORAGE_KEY = "parserias.username";
const PASSWORD_STORAGE_KEY = "parserias.password-cache";
const API_BASE_URL = (process.env.EXPO_PUBLIC_API_BASE_URL ?? "").trim();
const memoryStorage = new Map<string, string>();

async function safeGetItem(key: string): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(key);
  } catch {
    if (Platform.OS === "web") {
      try {
        return globalThis.localStorage?.getItem(key) ?? null;
      } catch {
        return null;
      }
    }
    return memoryStorage.get(key) ?? null;
  }
}

async function safeSetItem(key: string, value: string): Promise<void> {
  try {
    await AsyncStorage.setItem(key, value);
    return;
  } catch {
    if (Platform.OS === "web") {
      try {
        globalThis.localStorage?.setItem(key, value);
        return;
      } catch {
        // Fall back to in-memory storage below.
      }
    }
    memoryStorage.set(key, value);
  }
}

async function safeRemoveItem(key: string): Promise<void> {
  try {
    await AsyncStorage.removeItem(key);
    return;
  } catch {
    if (Platform.OS === "web") {
      try {
        globalThis.localStorage?.removeItem(key);
      } catch {
        // Fall back to in-memory removal below.
      }
    }
    memoryStorage.delete(key);
  }
}

const featuredArticles: FeaturedArticle[] = [
  {
    id: 1,
    title: "Parcerias locais e cooperacao",
    audience: "AIFAESA, IP",
    snippet: "Resumo das novas iniciativas para articulação institucional.",
    content:
      "A cooperação local entre instituições públicas e organizações parceiras é um dos pilares para melhorar a execução de políticas, acelerar a resposta a problemas concretos e reforçar a confiança dos cidadãos no trabalho institucional. No caso da AIFAESA e da INDIMO, a colaboração pode ser organizada a partir de frentes simples e mensuráveis: partilha de informação técnica, alinhamento de prioridades, acompanhamento conjunto de casos e produção de materiais orientativos para os utilizadores finais.\n\n" +
      "Na prática, uma agenda comum pode começar com encontros periódicos entre equipas técnicas, definição de um calendário de atividades e criação de um canal de comunicação direta para pedidos urgentes. Esse canal deve ser acompanhado por um registo mínimo de decisões, de forma a evitar duplicações, perda de informação e atrasos desnecessários. A linguagem dos documentos também deve ser padronizada para que cada organização consiga compreender rapidamente o que foi acordado e quais são os próximos passos.\n\n" +
      "Outro ponto importante é o reforço da visibilidade pública das iniciativas. Quando os parceiros trabalham de forma coordenada, é útil publicar notas curtas, relatórios de progresso e listas de prioridades já concluídas. Isso ajuda a manter o foco na execução e permite que os beneficiários percebam, de forma concreta, que o trabalho conjunto está a gerar resultados.\n\n" +
      "Por fim, a cooperação só se sustenta quando existe continuidade. A rotatividade de pessoas e mudanças de contexto são normais, por isso cada iniciativa deve ter um pequeno histórico, um responsável e indicadores simples de acompanhamento. Com isso, AIFAESA e INDIMO podem transformar a cooperação local num mecanismo estável de melhoria institucional.",
    institutionIds: [1, 2],
    targetInstitutions: ["AIFAESA", "INDIMO"],
  },
  {
    id: 2,
    title: "Gestao de comentarios e participacao",
    audience: "Todos os publicos permitidos",
    snippet: "Acompanhe respostas, discussoes e feedback do leitor.",
    content:
      "A gestão de comentários e de participação do público deve ser tratada como uma parte central da comunicação institucional, e não apenas como um recurso secundário do portal. Quando as respostas são organizadas de forma clara, os leitores compreendem melhor o objetivo de cada artigo, conseguem deixar sugestões mais úteis e sentem que a instituição está realmente a acompanhar as preocupações apresentadas. Isso melhora a qualidade do diálogo e reduz a dispersão de mensagens repetidas ou pouco informativas.\n\n" +
      "Um bom modelo de participação inclui regras simples. Os comentários devem ser moderados com rapidez, mas sem bloquear o debate legítimo. As respostas institucionais precisam ser objetivas, respeitosas e orientadas para a resolução do problema ou para o esclarecimento solicitado. Sempre que possível, é útil indicar prazos, pontos de contacto e o estado da análise. Essa transparência evita frustração e fortalece a credibilidade da plataforma.\n\n" +
      "Também é importante categorizar os comentários por tema, prioridade ou instituição responsável. Essa organização permite que os administradores identifiquem padrões de perguntas, percebam onde há dúvidas recorrentes e ajustem os artigos seguintes para responder com mais precisão às necessidades do público. O portal passa então a funcionar como um espaço de aprendizagem contínua.\n\n" +
      "Quando bem gerida, a participação não gera apenas mais mensagens. Ela produz conhecimento institucional. As dúvidas mais frequentes revelam falhas de comunicação, os elogios indicam práticas que devem ser mantidas e as críticas mostram onde o serviço precisa melhorar. Por isso, o comentário deve ser visto como uma ferramenta de gestão e de melhoria permanente.",
    institutionIds: [],
    targetInstitutions: ["ALL"],
  },
  {
    id: 3,
    title: "Actualizacoes administrativas",
    audience: "Admin",
    snippet: "Gestao de utilizadores, artigos e instituicoes num so lugar.",
    content:
      "As atualizações administrativas são necessárias para manter a plataforma coerente, segura e fácil de usar. Numa aplicação institucional, os administradores precisam de um espaço único para gerir utilizadores, artigos, instituições, permissões e conteúdos sensíveis. Sem essa centralização, cada alteração exige múltiplas operações e aumenta o risco de inconsistências entre o que o utilizador vê e o que está guardado na base de dados.\n\n" +
      "Um painel administrativo eficiente deve permitir que os dados mais importantes sejam alterados rapidamente, sem interromper o fluxo de trabalho. Isso inclui a criação de novos perfis, a revisão de contas antigas, a remoção de instituições desativadas e a atualização de publicações que já não refletem o estado atual da organização. Quando estas tarefas estão bem estruturadas, o portal torna-se mais previsível e menos dependente de manutenção manual fora do sistema.\n\n" +
      "A segurança é outro elemento decisivo. Acesso por função, validação dos campos, proteção contra duplicação de dados e registo de atividades ajudam a manter a integridade do sistema. Mesmo numa interface simples, estes mecanismos garantem que cada utilizador tem acesso apenas ao que lhe compete e que as alterações críticas podem ser auditadas mais tarde.\n\n" +
      "Por fim, a experiência do administrador deve ser rápida e objetiva. Botões de criação, edição e eliminação precisam de feedback imediato, e cada secção deve mostrar apenas a informação relevante. Dessa forma, as atualizações administrativas deixam de ser uma tarefa dispersa e passam a ser um processo controlado, previsível e fácil de manter.",
    institutionIds: [],
    targetInstitutions: [],
    adminOnly: true,
  },
];

const quickActions: QuickAction[] = [
  { id: "articles", label: "Artigos", icon: "document-text-outline", roles: ["admin", "user"] },
  { id: "users", label: "Users Management", icon: "people-outline", roles: ["admin"] },
  { id: "institutions", label: "Institutions", icon: "business-outline", roles: ["admin"] },
  { id: "profile", label: "Profile", icon: "person-circle-outline", roles: ["admin", "user"] },
];

const initialInstitutions: InstitutionItem[] = [
  { id: 1, name: "AIFAESA" },
  { id: 2, name: "INDIMO" },
  { id: 3, name: "MAE" },
  { id: 4, name: "PAM" },
  { id: 5, name: "DNRKPK-MIC" },
  { id: 6, name: "DNRKPK-MCI" },
];

const demoUsers: Record<string, { password: string; role: UserRole; institution: string }> = {
  admin: { password: "admin123", role: "admin", institution: "AIFAESA" },
  sgclobo: { password: "admin123", role: "admin", institution: "AIFAESA" },
  drsergio: { password: "user123", role: "user", institution: "AIFAESA" },
};

function buildUserProfile(inputUsername: string, inputPassword: string): AppUser | null {
  const cleanedUsername = inputUsername.trim();
  if (!cleanedUsername) {
    return null;
  }

  const key = cleanedUsername.toLowerCase();
  const knownUser = demoUsers[key];

  if (knownUser) {
    if (knownUser.password !== inputPassword.trim()) {
      return null;
    }
    return {
      username: cleanedUsername,
      role: knownUser.role,
      institution: knownUser.institution,
    };
  }

  return {
    username: cleanedUsername,
    role: "user",
    institution: "AIFAESA",
  };
}

function filterVisibleArticles(role: UserRole, institution: string): FeaturedArticle[] {
  return featuredArticles.filter((article) => {
    if (role === "admin") {
      return true;
    }

    if (article.adminOnly) {
      return false;
    }

    return article.targetInstitutions.includes("ALL") || article.targetInstitutions.includes(institution);
  });
}

function buildMobileApiUrl(action: string): string {
  const normalizedBase = API_BASE_URL.endsWith("/") ? API_BASE_URL.slice(0, -1) : API_BASE_URL;
  const apiRoot = normalizedBase.endsWith("/web") ? normalizedBase : `${normalizedBase}/web`;
  return `${apiRoot}/api/mobile.php?action=${encodeURIComponent(action)}`;
}

function remoteArticleToFeatured(article: RemoteArticle): FeaturedArticle {
  const institutionNames = (article.institution_names ?? "").trim();
  const institutionIds = Array.isArray(article.institution_ids) ? article.institution_ids : [];
  const targetInstitutions = institutionIds.length
    ? institutionNames.split(",").map((name) => name.trim()).filter(Boolean)
    : ["ALL"];

  return {
    id: article.id,
    title: article.title,
    audience: institutionNames || "Todos os públicos",
    snippet: article.snippet?.trim() || article.content.trim().slice(0, 140),
    content: article.content?.trim() || article.snippet?.trim() || article.title,
    institutionIds,
    targetInstitutions,
  };
}

type PortalBootstrapResponse = {
  success: true;
  token?: string;
  user: AppUser & { email?: string; id?: number; institution_id?: number | null };
  articles: RemoteArticle[];
  institutions: RemoteInstitution[];
  users?: Array<{
    id: number;
    name: string;
    position: string;
    email: string;
    whatsapp: string;
    role: UserRole;
    institution_id: number | null;
    institution_name?: string | null;
    created_at?: string;
    last_login?: string | null;
  }>;
};

type RemoteUserRow = {
  id: number;
  name: string;
  position: string;
  email: string;
  whatsapp: string;
  role: UserRole;
  institution_id: number | null;
  institution_name?: string | null;
  created_at?: string;
  last_login?: string | null;
};

function mapRemoteUser(user: PortalBootstrapResponse["user"]): AppUser {
  return {
    username: user.username,
    role: user.role,
    institution: user.institution || "AIFAESA",
  };
}

function mapRemoteArticles(articles: RemoteArticle[]): FeaturedArticle[] {
  return articles.map(remoteArticleToFeatured);
}

function mapRemoteInstitutions(institutionsList: RemoteInstitution[]): InstitutionItem[] {
  return institutionsList.map((institution) => ({ id: institution.id, name: institution.name }));
}

function mapRemoteUsers(users: RemoteUserRow[]): RemoteUserRow[] {
  return users.map((user) => ({ ...user }));
}

export default function Index() {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [restoredPassword, setRestoredPassword] = useState(false);
  const [isBooting, setIsBooting] = useState(true);
  const [isSignedIn, setIsSignedIn] = useState(false);
  const [currentUser, setCurrentUser] = useState<AppUser | null>(null);
  const [activeTab, setActiveTab] = useState<DashboardTab>("articles");
  const [sessionToken, setSessionToken] = useState("");
  const [articles, setArticles] = useState<FeaturedArticle[]>(featuredArticles);
  const [institutions, setInstitutions] = useState<InstitutionItem[]>(initialInstitutions);
  const [users, setUsers] = useState<RemoteUserRow[]>([]);
  const [institutionNameInput, setInstitutionNameInput] = useState("");
  const [editingInstitutionId, setEditingInstitutionId] = useState<number | null>(null);
  const [isPortalLoading, setIsPortalLoading] = useState(false);
  const [selectedArticle, setSelectedArticle] = useState<FeaturedArticle | null>(null);
  const [profileName, setProfileName] = useState("");
  const [profilePosition, setProfilePosition] = useState("");
  const [profileEmail, setProfileEmail] = useState("");
  const [profileWhatsapp, setProfileWhatsapp] = useState("");
  const [profileCurrentPassword, setProfileCurrentPassword] = useState("");
  const [profileNewPassword, setProfileNewPassword] = useState("");
  const [editingUserId, setEditingUserId] = useState<number | null>(null);
  const [userNameInput, setUserNameInput] = useState("");
  const [userPositionInput, setUserPositionInput] = useState("");
  const [userEmailInput, setUserEmailInput] = useState("");
  const [userWhatsappInput, setUserWhatsappInput] = useState("");
  const [userRoleInput, setUserRoleInput] = useState<UserRole>("user");
  const [userInstitutionIdInput, setUserInstitutionIdInput] = useState("");
  const [userPasswordInput, setUserPasswordInput] = useState("");
  const [editingArticleId, setEditingArticleId] = useState<number | null>(null);
  const [articleTitleInput, setArticleTitleInput] = useState("");
  const [articleContentInput, setArticleContentInput] = useState("");
  const [articleInstitutionIdsInput, setArticleInstitutionIdsInput] = useState("");

  const visibleActions = quickActions.filter((action) =>
    currentUser ? action.roles.includes(currentUser.role) : false
  );
  const visibleArticles = currentUser
    ? (currentUser.role === "admin"
        ? articles
        : articles.filter((article) => {
            if (article.adminOnly) {
              return false;
            }
            return article.targetInstitutions.includes("ALL") || article.targetInstitutions.includes(currentUser.institution);
          }))
    : [];

  function syncProfileForm(nextUser: AppUser | null) {
    const name = nextUser?.username ?? "";
    setProfileName(name);
    setProfilePosition(nextUser?.role === "admin" ? "Administrator" : "Member");
    setProfileEmail(nextUser ? `${nextUser.username}@example.com` : "");
    setProfileWhatsapp("");
    setProfileCurrentPassword("");
    setProfileNewPassword("");
  }

  function clearPortalState() {
    setCurrentUser(null);
    setIsSignedIn(false);
    setSessionToken("");
    setArticles(featuredArticles);
    setInstitutions(initialInstitutions);
    setUsers([]);
    setSelectedArticle(null);
    setActiveTab("articles");
    syncProfileForm(null);
  }

  async function loadRemoteBootstrap(token: string) {
    const response = await fetch(buildMobileApiUrl("bootstrap"), {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
      },
    });

    const payload = (await response.json()) as Partial<PortalBootstrapResponse> & { error?: string };
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || "Unable to load portal data.");
    }

    setCurrentUser(mapRemoteUser(payload.user));
    setArticles(mapRemoteArticles(payload.articles ?? []));
    setInstitutions(mapRemoteInstitutions(payload.institutions ?? []));
    setUsers(mapRemoteUsers(payload.users ?? []));
    setSessionToken(token);
    setIsSignedIn(true);
    setActiveTab("articles");
    syncProfileForm(mapRemoteUser(payload.user));
  }

  async function loginRemote(identifier: string, passwordValue: string) {
    const response = await fetch(buildMobileApiUrl("login"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ identifier, password: passwordValue }),
    });

    const payload = (await response.json()) as Partial<PortalBootstrapResponse> & { error?: string };
    if (!response.ok || !payload.success || !payload.token) {
      throw new Error(payload.error || "Invalid email or password.");
    }

    setCurrentUser(mapRemoteUser(payload.user));
    setArticles(mapRemoteArticles(payload.articles ?? []));
    setInstitutions(mapRemoteInstitutions(payload.institutions ?? []));
    setUsers(mapRemoteUsers(payload.users ?? []));
    setSessionToken(payload.token);
    setIsSignedIn(true);
    setActiveTab("articles");
    syncProfileForm(mapRemoteUser(payload.user));
    return payload.token;
  }

  async function saveRemoteInstitution(operation: "create" | "update" | "delete", name?: string, id?: number) {
    if (!sessionToken) {
      throw new Error("No active portal session.");
    }

    const response = await fetch(buildMobileApiUrl("institutions"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${sessionToken}`,
      },
      body: JSON.stringify({ operation, name, id }),
    });

    const payload = (await response.json()) as { success?: boolean; institutions?: RemoteInstitution[]; error?: string };
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || "Unable to update institutions.");
    }

    if (payload.institutions) {
      setInstitutions(mapRemoteInstitutions(payload.institutions));
    }
  }

  function parseInstitutionIdsFromInput(value: string): number[] {
    return value
      .split(",")
      .map((chunk) => chunk.trim())
      .filter((chunk) => /^\d+$/.test(chunk))
      .map((chunk) => Number.parseInt(chunk, 10));
  }

  function buildAudienceFromInstitutionIds(ids: number[]): { audience: string; targets: string[] } {
    if (!ids.length) {
      return { audience: "Todos os públicos", targets: ["ALL"] };
    }

    const names = institutions
      .filter((institution) => ids.includes(institution.id))
      .map((institution) => institution.name);

    return {
      audience: names.length ? names.join(", ") : "Todos os públicos",
      targets: names.length ? names : ["ALL"],
    };
  }

  function resetUserForm() {
    setEditingUserId(null);
    setUserNameInput("");
    setUserPositionInput("");
    setUserEmailInput("");
    setUserWhatsappInput("");
    setUserRoleInput("user");
    setUserInstitutionIdInput("");
    setUserPasswordInput("");
  }

  function resetArticleForm() {
    setEditingArticleId(null);
    setArticleTitleInput("");
    setArticleContentInput("");
    setArticleInstitutionIdsInput("");
  }

  async function saveRemoteUser(operation: "create" | "update" | "delete", id?: number) {
    if (!sessionToken) {
      throw new Error("No active portal session.");
    }

    const payloadBody: Record<string, unknown> = { operation };
    if (id) {
      payloadBody.id = id;
    }
    if (operation !== "delete") {
      payloadBody.name = userNameInput.trim();
      payloadBody.position = userPositionInput.trim();
      payloadBody.email = userEmailInput.trim();
      payloadBody.whatsapp = userWhatsappInput.trim();
      payloadBody.role = userRoleInput;
      payloadBody.password = userPasswordInput;
      payloadBody.institution_id = userInstitutionIdInput.trim() || null;
    }

    const response = await fetch(buildMobileApiUrl("users"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${sessionToken}`,
      },
      body: JSON.stringify(payloadBody),
    });

    const payload = (await response.json()) as { success?: boolean; users?: RemoteUserRow[]; error?: string };
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || "Unable to save user.");
    }

    setUsers(mapRemoteUsers(payload.users ?? []));
  }

  async function saveRemoteArticle(operation: "create" | "update" | "delete", id?: number) {
    if (!sessionToken) {
      throw new Error("No active portal session.");
    }

    const payloadBody: Record<string, unknown> = { operation };
    if (id) {
      payloadBody.id = id;
    }
    if (operation !== "delete") {
      payloadBody.title = articleTitleInput.trim();
      payloadBody.content = articleContentInput.trim();
      payloadBody.institution_ids = parseInstitutionIdsFromInput(articleInstitutionIdsInput);
    }

    const response = await fetch(buildMobileApiUrl("articles"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${sessionToken}`,
      },
      body: JSON.stringify(payloadBody),
    });

    const payload = (await response.json()) as { success?: boolean; articles?: RemoteArticle[]; error?: string };
    if (!response.ok || !payload.success) {
      throw new Error(payload.error || "Unable to save article.");
    }

    setArticles(mapRemoteArticles(payload.articles ?? []));
  }

  async function saveRemoteProfile() {
    if (!API_BASE_URL || !sessionToken) {
      const nextUser = currentUser
        ? { ...currentUser, username: profileName.trim() || currentUser.username }
        : null;
      if (nextUser) {
        setCurrentUser(nextUser);
      }

      setRestoredPassword(false);
      return;
    }

    const response = await fetch(buildMobileApiUrl("profile"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${sessionToken}`,
      },
      body: JSON.stringify({
        name: profileName,
        position: profilePosition,
        email: profileEmail,
        whatsapp: profileWhatsapp,
        current_password: profileCurrentPassword,
        new_password: profileNewPassword,
      }),
    });

    const payload = (await response.json()) as { success?: boolean; user?: PortalBootstrapResponse["user"]; error?: string };
    if (!response.ok || !payload.success || !payload.user) {
      throw new Error(payload.error || "Unable to save profile.");
    }

    const nextUser = mapRemoteUser(payload.user);
    setCurrentUser(nextUser);
    setPassword(profileNewPassword || password);
    setRestoredPassword(false);
    syncProfileForm(nextUser);
  }

  function selectArticle(article: FeaturedArticle) {
    setSelectedArticle(article);
  }

  function dismissArticle() {
    setSelectedArticle(null);
  }

  async function restoreCachedCredentials() {
    const savedUsername = (await safeGetItem(USERNAME_STORAGE_KEY)) ?? "";
    setUsername(savedUsername);

    const rawPasswordCache = await safeGetItem(PASSWORD_STORAGE_KEY);
    if (!rawPasswordCache) {
      setPassword("");
      setRestoredPassword(false);
      return;
    }

    try {
      const cache = JSON.parse(rawPasswordCache) as PasswordCache;
      const age = Date.now() - cache.savedAt;

      if (cache.username === savedUsername && cache.password && age < SESSION_WINDOW_MS) {
        setPassword(cache.password);
        setRestoredPassword(true);

        if (API_BASE_URL && cache.token) {
          try {
            await loadRemoteBootstrap(cache.token);
            return;
          } catch {
            await safeRemoveItem(PASSWORD_STORAGE_KEY);
          }
        }

        if (API_BASE_URL && cache.password) {
          try {
            await loginRemote(cache.username, cache.password);
            return;
          } catch {
            await safeRemoveItem(PASSWORD_STORAGE_KEY);
          }
        }

        return;
      }

      await safeRemoveItem(PASSWORD_STORAGE_KEY);
      setPassword("");
      setRestoredPassword(false);
    } catch {
      await safeRemoveItem(PASSWORD_STORAGE_KEY);
      setPassword("");
      setRestoredPassword(false);
    }
  }

  useEffect(() => {
    let mounted = true;

    async function restoreSession() {
      try {
        if (!mounted) {
          return;
        }
        await restoreCachedCredentials();
      } catch {
        await safeRemoveItem(USERNAME_STORAGE_KEY);
        await safeRemoveItem(PASSWORD_STORAGE_KEY);
      } finally {
        if (mounted) {
          setIsBooting(false);
        }
      }
    }

    restoreSession();

    return () => {
      mounted = false;
    };
  }, []);

  async function persistSession(nextUsername: string, nextPassword: string, nextToken?: string) {
    const payload: PasswordCache = {
      username: nextUsername,
      password: nextPassword,
      savedAt: Date.now(),
      token: nextToken,
    };
    await safeSetItem(USERNAME_STORAGE_KEY, nextUsername);
    await safeSetItem(PASSWORD_STORAGE_KEY, JSON.stringify(payload));
  }

  async function handleSignIn() {
    const cleanedUsername = username.trim();
    const cleanedPassword = password.trim();

    if (!cleanedUsername || !cleanedPassword) {
      Alert.alert("Missing data", "Enter your username and password to continue.");
      return;
    }

    let remoteToken: string | undefined;

    try {
      if (API_BASE_URL) {
        remoteToken = await loginRemote(cleanedUsername, cleanedPassword);
      } else {
        const nextUser = buildUserProfile(cleanedUsername, cleanedPassword);
        if (!nextUser) {
          Alert.alert("Invalid credentials", "The username or password is not correct.");
          return;
        }

        setCurrentUser(nextUser);
        setArticles(featuredArticles);
        setInstitutions(initialInstitutions);
        setActiveTab("articles");
        setIsSignedIn(true);
      }

      await persistSession(cleanedUsername, cleanedPassword, remoteToken ?? sessionToken);
    } catch (error) {
      Alert.alert(
        "Login failed",
        error instanceof Error ? error.message : "The username or password is not correct."
      );
      return;
    }
  }

  async function handleSignOut() {
    clearPortalState();
    setPassword("");
    setRestoredPassword(false);
  }

  function resetInstitutionForm() {
    setInstitutionNameInput("");
    setEditingInstitutionId(null);
  }

  function handleSaveInstitution() {
    const name = institutionNameInput.trim();
    if (!name) {
      Alert.alert("Missing data", "Enter an institution name.");
      return;
    }

    (async () => {
      try {
        if (API_BASE_URL && sessionToken) {
          await saveRemoteInstitution(editingInstitutionId === null ? "create" : "update", name, editingInstitutionId ?? undefined);
        } else {
          setInstitutions((current) => {
            if (editingInstitutionId === null) {
              const nextId = current.length ? Math.max(...current.map((item) => item.id)) + 1 : 1;
              return [...current, { id: nextId, name }];
            }

            return current.map((item) =>
              item.id === editingInstitutionId ? { ...item, name } : item
            );
          });
        }

        resetInstitutionForm();
      } catch (error) {
        Alert.alert("Institution error", error instanceof Error ? error.message : "Unable to save institution.");
      }
    })();
  }

  function handleEditInstitution(item: InstitutionItem) {
    setEditingInstitutionId(item.id);
    setInstitutionNameInput(item.name);
  }

  function handleDeleteInstitution(id: number) {
    (async () => {
      try {
        if (API_BASE_URL && sessionToken) {
          await saveRemoteInstitution("delete", undefined, id);
        } else {
          setInstitutions((current) => current.filter((item) => item.id !== id));
        }

        if (editingInstitutionId === id) {
          resetInstitutionForm();
        }
      } catch (error) {
        Alert.alert("Institution error", error instanceof Error ? error.message : "Unable to delete institution.");
      }
    })();
  }

  function handleEditUser(user: RemoteUserRow) {
    setEditingUserId(user.id);
    setUserNameInput(user.name);
    setUserPositionInput(user.position || "");
    setUserEmailInput(user.email);
    setUserWhatsappInput(user.whatsapp || "");
    setUserRoleInput(user.role);
    setUserInstitutionIdInput(user.institution_id ? String(user.institution_id) : "");
    setUserPasswordInput("");
  }

  function handleSaveUser() {
    if (!userNameInput.trim() || !userEmailInput.trim()) {
      Alert.alert("Missing data", "Name and email are required.");
      return;
    }

    (async () => {
      try {
        if (API_BASE_URL && sessionToken) {
          await saveRemoteUser(editingUserId === null ? "create" : "update", editingUserId ?? undefined);
        } else {
          setUsers((current) => {
            if (editingUserId === null) {
              const nextId = current.length ? Math.max(...current.map((item) => item.id)) + 1 : 1;
              return [
                ...current,
                {
                  id: nextId,
                  name: userNameInput.trim(),
                  position: userPositionInput.trim(),
                  email: userEmailInput.trim(),
                  whatsapp: userWhatsappInput.trim(),
                  role: userRoleInput,
                  institution_id: userInstitutionIdInput.trim() ? Number.parseInt(userInstitutionIdInput.trim(), 10) : null,
                  institution_name:
                    institutions.find((institution) => institution.id === Number.parseInt(userInstitutionIdInput.trim() || "0", 10))?.name ?? null,
                },
              ];
            }

            return current.map((item) =>
              item.id === editingUserId
                ? {
                    ...item,
                    name: userNameInput.trim(),
                    position: userPositionInput.trim(),
                    email: userEmailInput.trim(),
                    whatsapp: userWhatsappInput.trim(),
                    role: userRoleInput,
                    institution_id: userInstitutionIdInput.trim() ? Number.parseInt(userInstitutionIdInput.trim(), 10) : null,
                    institution_name:
                      institutions.find((institution) => institution.id === Number.parseInt(userInstitutionIdInput.trim() || "0", 10))?.name ?? null,
                  }
                : item
            );
          });
        }

        resetUserForm();
      } catch (error) {
        Alert.alert("Users error", error instanceof Error ? error.message : "Unable to save user.");
      }
    })();
  }

  function handleDeleteUser(id: number) {
    (async () => {
      try {
        if (API_BASE_URL && sessionToken) {
          await saveRemoteUser("delete", id);
        } else {
          setUsers((current) => current.filter((item) => item.id !== id));
        }

        if (editingUserId === id) {
          resetUserForm();
        }
      } catch (error) {
        Alert.alert("Users error", error instanceof Error ? error.message : "Unable to delete user.");
      }
    })();
  }

  function handleEditArticle(article: FeaturedArticle) {
    setEditingArticleId(article.id);
    setArticleTitleInput(article.title);
    setArticleContentInput(article.content || article.snippet);
    setArticleInstitutionIdsInput(article.institutionIds.join(","));
  }

  function handleSaveArticle() {
    const title = articleTitleInput.trim();
    const content = articleContentInput.trim();
    if (!title || !content) {
      Alert.alert("Missing data", "Article title and content are required.");
      return;
    }

    (async () => {
      try {
        if (API_BASE_URL && sessionToken) {
          await saveRemoteArticle(editingArticleId === null ? "create" : "update", editingArticleId ?? undefined);
        } else {
          const ids = parseInstitutionIdsFromInput(articleInstitutionIdsInput);
          const audienceData = buildAudienceFromInstitutionIds(ids);

          setArticles((current) => {
            if (editingArticleId === null) {
              const nextId = current.length ? Math.max(...current.map((item) => item.id)) + 1 : 1;
              return [
                {
                  id: nextId,
                  title,
                  content,
                  snippet: content.slice(0, 140),
                  audience: audienceData.audience,
                  institutionIds: ids,
                  targetInstitutions: audienceData.targets,
                },
                ...current,
              ];
            }

            return current.map((item) =>
              item.id === editingArticleId
                ? {
                    ...item,
                    title,
                    content,
                    snippet: content.slice(0, 140),
                    audience: audienceData.audience,
                    institutionIds: ids,
                    targetInstitutions: audienceData.targets,
                  }
                : item
            );
          });
        }

        resetArticleForm();
      } catch (error) {
        Alert.alert("Articles error", error instanceof Error ? error.message : "Unable to save article.");
      }
    })();
  }

  function handleDeleteArticle(id: number) {
    (async () => {
      try {
        if (API_BASE_URL && sessionToken) {
          await saveRemoteArticle("delete", id);
        } else {
          setArticles((current) => current.filter((item) => item.id !== id));
        }

        if (editingArticleId === id) {
          resetArticleForm();
        }
        if (selectedArticle?.id === id) {
          setSelectedArticle(null);
        }
      } catch (error) {
        Alert.alert("Articles error", error instanceof Error ? error.message : "Unable to delete article.");
      }
    })();
  }

  function renderActiveModule() {
    if (!currentUser) {
      return null;
    }

    if (activeTab === "profile") {
      return (
        <View style={styles.sectionBlock}>
          <Text style={styles.sectionTitle}>Profile</Text>
          <View style={styles.moduleCard}>
            <View style={styles.fieldBlock}>
              <Text style={styles.label}>Name</Text>
              <TextInput style={styles.input} value={profileName} onChangeText={setProfileName} />
            </View>
            <View style={styles.fieldBlock}>
              <Text style={styles.label}>Position</Text>
              <TextInput style={styles.input} value={profilePosition} onChangeText={setProfilePosition} />
            </View>
            <View style={styles.fieldBlock}>
              <Text style={styles.label}>Email</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                style={styles.input}
                value={profileEmail}
                onChangeText={setProfileEmail}
              />
            </View>
            <View style={styles.fieldBlock}>
              <Text style={styles.label}>Whatsapp</Text>
              <TextInput style={styles.input} value={profileWhatsapp} onChangeText={setProfileWhatsapp} />
            </View>
            <View style={styles.fieldBlock}>
              <Text style={styles.label}>Current password</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                secureTextEntry
                style={styles.input}
                value={profileCurrentPassword}
                onChangeText={setProfileCurrentPassword}
              />
            </View>
            <View style={styles.fieldBlock}>
              <Text style={styles.label}>New password</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                secureTextEntry
                style={styles.input}
                value={profileNewPassword}
                onChangeText={setProfileNewPassword}
              />
            </View>
            <View style={styles.inlineFormActions}>
              <Pressable style={styles.secondaryButton} onPress={() => saveRemoteProfile().catch((error) => Alert.alert("Profile error", error instanceof Error ? error.message : "Unable to save profile."))}>
                <Text style={styles.secondaryButtonText}>Save profile</Text>
              </Pressable>
              <Pressable style={styles.ghostButton} onPress={() => syncProfileForm(currentUser)}>
                <Text style={styles.ghostButtonText}>Reset</Text>
              </Pressable>
            </View>
          </View>
        </View>
      );
    }

    if (activeTab === "users" && currentUser.role === "admin") {
      return (
        <View style={styles.sectionBlock}>
          <Text style={styles.sectionTitle}>Users Management</Text>
          <View style={styles.moduleCard}>
            <Text style={styles.moduleBody}>Create, edit and delete users directly from the app.</Text>
            <View style={styles.inlineForm}>
              <TextInput
                placeholder="Name"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={userNameInput}
                onChangeText={setUserNameInput}
              />
              <TextInput
                placeholder="Position"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={userPositionInput}
                onChangeText={setUserPositionInput}
              />
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="Email"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={userEmailInput}
                onChangeText={setUserEmailInput}
              />
              <TextInput
                placeholder="Whatsapp"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={userWhatsappInput}
                onChangeText={setUserWhatsappInput}
              />
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="Role (admin or user)"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={userRoleInput}
                onChangeText={(value) => setUserRoleInput(value.trim().toLowerCase() === "admin" ? "admin" : "user")}
              />
              <TextInput
                keyboardType="numeric"
                placeholder="Institution ID"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={userInstitutionIdInput}
                onChangeText={setUserInstitutionIdInput}
              />
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                placeholder={editingUserId === null ? "Password" : "Password (optional)"}
                placeholderTextColor="#8796a8"
                secureTextEntry
                style={styles.input}
                value={userPasswordInput}
                onChangeText={setUserPasswordInput}
              />
              <View style={styles.inlineFormActions}>
                <Pressable style={styles.secondaryButton} onPress={handleSaveUser}>
                  <Text style={styles.secondaryButtonText}>{editingUserId === null ? "Add user" : "Save user"}</Text>
                </Pressable>
                {editingUserId !== null ? (
                  <Pressable style={styles.ghostButton} onPress={resetUserForm}>
                    <Text style={styles.ghostButtonText}>Cancel</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
            <View style={styles.listStack}>
              {users.map((item) => (
                <View key={item.id} style={styles.listRow}>
                  <View>
                    <Text style={styles.listTitle}>{item.name}</Text>
                    <Text style={styles.listMeta}>{item.email}</Text>
                    <Text style={styles.listMeta}>
                      {item.role} • {item.institution_name || "No institution"}
                    </Text>
                  </View>
                  <View style={styles.rowActions}>
                    <Pressable style={styles.rowActionButton} onPress={() => handleEditUser(item)}>
                      <Text style={styles.rowActionText}>Edit</Text>
                    </Pressable>
                    <Pressable style={styles.rowActionButtonDanger} onPress={() => handleDeleteUser(item.id)}>
                      <Text style={styles.rowActionTextDanger}>Delete</Text>
                    </Pressable>
                  </View>
                </View>
              ))}
            </View>
          </View>
        </View>
      );
    }

    if (activeTab === "institutions" && currentUser.role === "admin") {
      return (
        <View style={styles.sectionBlock}>
          <Text style={styles.sectionTitle}>Institutions</Text>
          <View style={styles.moduleCard}>
            <Text style={styles.moduleBody}>
              Add, edit and remove institutions directly in the app.
            </Text>
            <View style={styles.inlineForm}>
              <TextInput
                autoCapitalize="characters"
                autoCorrect={false}
                placeholder="Institution name"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={institutionNameInput}
                onChangeText={setInstitutionNameInput}
              />
              <View style={styles.inlineFormActions}>
                <Pressable style={styles.secondaryButton} onPress={handleSaveInstitution}>
                  <Text style={styles.secondaryButtonText}>
                    {editingInstitutionId === null ? "Add" : "Save"}
                  </Text>
                </Pressable>
                {editingInstitutionId !== null ? (
                  <Pressable style={styles.ghostButton} onPress={resetInstitutionForm}>
                    <Text style={styles.ghostButtonText}>Cancel</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
            <View style={styles.listStack}>
              {institutions.map((item) => (
                <View key={item.id} style={styles.listRow}>
                  <View>
                    <Text style={styles.listTitle}>{item.name}</Text>
                    <Text style={styles.listMeta}>Institution #{item.id}</Text>
                  </View>
                  <View style={styles.rowActions}>
                    <Pressable style={styles.rowActionButton} onPress={() => handleEditInstitution(item)}>
                      <Text style={styles.rowActionText}>Edit</Text>
                    </Pressable>
                    <Pressable style={styles.rowActionButtonDanger} onPress={() => handleDeleteInstitution(item.id)}>
                      <Text style={styles.rowActionTextDanger}>Delete</Text>
                    </Pressable>
                  </View>
                </View>
              ))}
            </View>
          </View>
        </View>
      );
    }

    return (
      <View style={styles.sectionBlock}>
        <Text style={styles.sectionTitle}>Featured articles</Text>
        {visibleArticles.map((article) => (
          <Pressable key={article.id} style={styles.articleCard} onPress={() => selectArticle(article)}>
            <View style={styles.articleTopRow}>
              <Text style={styles.articleTitle}>{article.title}</Text>
              <Text style={styles.articleAudience}>{article.audience}</Text>
            </View>
            <Text style={styles.articleSnippet}>{article.snippet}</Text>
          </Pressable>
        ))}
        {selectedArticle ? (
          <View style={styles.articleDetailCard}>
            <View style={styles.articleTopRow}>
              <Text style={styles.moduleTitle}>{selectedArticle.title}</Text>
              <Pressable onPress={dismissArticle}>
                <Text style={styles.closeText}>Close</Text>
              </Pressable>
            </View>
            <Text style={styles.articleDetailAudience}>{selectedArticle.audience}</Text>
            <Text style={styles.articleDetailContent}>{selectedArticle.content || selectedArticle.snippet}</Text>
          </View>
        ) : null}

        {currentUser.role === "admin" ? (
          <View style={styles.moduleCard}>
            <Text style={styles.moduleTitle}>Article Management</Text>
            <Text style={styles.moduleBody}>Add, edit and delete articles.</Text>
            <View style={styles.inlineForm}>
              <TextInput
                placeholder="Article title"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={articleTitleInput}
                onChangeText={setArticleTitleInput}
              />
              <TextInput
                placeholder="Institution IDs (comma-separated, optional)"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={articleInstitutionIdsInput}
                onChangeText={setArticleInstitutionIdsInput}
              />
              <TextInput
                multiline
                textAlignVertical="top"
                placeholder="Article content"
                placeholderTextColor="#8796a8"
                style={[styles.input, styles.multilineInput]}
                value={articleContentInput}
                onChangeText={setArticleContentInput}
              />
              <View style={styles.inlineFormActions}>
                <Pressable style={styles.secondaryButton} onPress={handleSaveArticle}>
                  <Text style={styles.secondaryButtonText}>
                    {editingArticleId === null ? "Add article" : "Save article"}
                  </Text>
                </Pressable>
                {editingArticleId !== null ? (
                  <Pressable style={styles.ghostButton} onPress={resetArticleForm}>
                    <Text style={styles.ghostButtonText}>Cancel</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>

            <View style={styles.listStack}>
              {articles.map((article) => (
                <View key={`manage-${article.id}`} style={styles.listRow}>
                  <View style={styles.flexGrowArea}>
                    <Text style={styles.listTitle}>{article.title}</Text>
                    <Text style={styles.listMeta}>{article.audience}</Text>
                  </View>
                  <View style={styles.rowActions}>
                    <Pressable style={styles.rowActionButton} onPress={() => handleEditArticle(article)}>
                      <Text style={styles.rowActionText}>Edit</Text>
                    </Pressable>
                    <Pressable style={styles.rowActionButtonDanger} onPress={() => handleDeleteArticle(article.id)}>
                      <Text style={styles.rowActionTextDanger}>Delete</Text>
                    </Pressable>
                  </View>
                </View>
              ))}
            </View>
          </View>
        ) : null}
      </View>
    );
  }

  if (isBooting) {
    return (
      <SafeAreaView style={styles.bootScreen}>
        <StatusBar barStyle="light-content" />
        <View style={styles.bootCard}>
          <View style={styles.logoMark}>
            <Ionicons name="document-text-outline" size={34} color="#f5dca7" />
          </View>
          <Text style={styles.bootTitle}>Parserias AIFAESA</Text>
          <Text style={styles.bootSubtitle}>Loading secure session...</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (isSignedIn) {
    return (
      <SafeAreaView style={styles.shell}>
        <StatusBar barStyle="light-content" />
        <ScrollView contentContainerStyle={styles.shellContent} showsVerticalScrollIndicator={false}>
          <View style={styles.shellHeader}>
            <View style={styles.brandRow}>
              <View style={styles.logoMarkSmall}>
                <Ionicons name="document-text-outline" size={18} color="#f5dca7" />
              </View>
              <View>
                <Text style={styles.brandName}>Parserias AIFAESA</Text>
                <Text style={styles.brandTag}>Mobile-first institutional portal</Text>
              </View>
            </View>
            <Pressable style={styles.signOutButton} onPress={handleSignOut}>
              <Ionicons name="log-out-outline" size={16} color="#0d2133" />
              <Text style={styles.signOutText}>Sign out</Text>
            </Pressable>
          </View>

          <View style={styles.heroCard}>
            <Text style={styles.heroKicker}>Authenticated preview</Text>
            <Text style={styles.heroTitle}>Welcome, {currentUser?.username || "user"}.</Text>
            <Text style={styles.heroBody}>
              {currentUser?.role === "admin"
                ? "Admin mode enabled: articles, users, institutions and profile tools are active."
                : "User mode enabled: only institution-targeted articles and profile are available."}
            </Text>
          </View>

          <View style={styles.quickGrid}>
            {visibleActions.map((action) => (
              <Pressable
                key={action.id}
                style={[styles.quickCard, activeTab === action.id ? styles.quickCardActive : undefined]}
                onPress={() => setActiveTab(action.id)}
              >
                <Ionicons name={action.icon} size={20} color="#c8973a" />
                <Text style={styles.quickLabel}>{action.label}</Text>
              </Pressable>
            ))}
          </View>

          {renderActiveModule()}
        </ScrollView>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.screen}>
      <StatusBar barStyle="light-content" />
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.glowOne} />
          <View style={styles.glowTwo} />

          <View style={styles.hero}
          >
            <View style={styles.logoMark}>
              <Ionicons name="document-text-outline" size={36} color="#f5dca7" />
            </View>
            <Text style={styles.kicker}>Institutional access</Text>
            <Text style={styles.title}>Parserias AIFAESA</Text>
            <Text style={styles.subtitle}>
              Sign in to see articles, comments, user profiles and institution-specific content.
            </Text>
          </View>

          <View style={styles.card}>
            <Text style={styles.cardTitle}>Login</Text>
            <Text style={styles.cardSubtitle}>
              Username stays stored on this device. Password is restored for up to 48 hours after
              the latest successful login.
            </Text>

            <View style={styles.fieldBlock}>
              <Text style={styles.label}>Username</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="sgclobo"
                placeholderTextColor="#8796a8"
                style={styles.input}
                value={username}
                onChangeText={setUsername}
              />
            </View>

            <View style={styles.fieldBlock}>
              <View style={styles.fieldHeader}>
                <Text style={styles.label}>Password</Text>
                {restoredPassword ? <Text style={styles.helperBadge}>Auto-filled</Text> : null}
              </View>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="••••••••"
                placeholderTextColor="#8796a8"
                secureTextEntry
                style={styles.input}
                value={password}
                onChangeText={setPassword}
              />
            </View>

            <Pressable style={styles.primaryButton} onPress={handleSignIn}>
              <Ionicons name="log-in-outline" size={18} color="#ffffff" />
              <Text style={styles.primaryButtonText}>Enter</Text>
            </Pressable>
          </View>

          <View style={styles.noteCard}>
            <Text style={styles.noteTitle}>What happens next</Text>
            <Text style={styles.noteText}>
              After sign-in, the dashboard changes by role. Admins get all modules; users get only
              articles for their institution and profile.
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  screen: {
    flex: 1,
    backgroundColor: "#081320",
  },
  scrollContent: {
    flexGrow: 1,
    paddingHorizontal: 20,
    paddingVertical: 18,
    justifyContent: "center",
  },
  glowOne: {
    position: "absolute",
    top: -90,
    right: -80,
    width: 220,
    height: 220,
    borderRadius: 110,
    backgroundColor: "rgba(200,151,58,0.18)",
  },
  glowTwo: {
    position: "absolute",
    bottom: -110,
    left: -90,
    width: 240,
    height: 240,
    borderRadius: 120,
    backgroundColor: "rgba(31,83,127,0.26)",
  },
  hero: {
    alignItems: "center",
    gap: 12,
    marginBottom: 18,
  },
  logoMark: {
    width: 92,
    height: 92,
    borderRadius: 28,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(255,255,255,0.09)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.18)",
  },
  kicker: {
    color: "#d9ae55",
    textTransform: "uppercase",
    letterSpacing: 2,
    fontSize: 11,
    fontWeight: "800",
  },
  title: {
    color: "#ffffff",
    fontSize: 34,
    lineHeight: 38,
    fontWeight: "900",
    textAlign: "center",
    fontFamily: Platform.select({ ios: "Avenir Next", default: undefined }),
  },
  subtitle: {
    color: "rgba(255,255,255,0.78)",
    fontSize: 15,
    lineHeight: 22,
    textAlign: "center",
    maxWidth: 360,
  },
  card: {
    backgroundColor: "rgba(255,255,255,0.96)",
    borderRadius: 28,
    padding: 18,
    gap: 14,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 20 },
    shadowOpacity: 0.22,
    shadowRadius: 30,
    elevation: 12,
  },
  cardTitle: {
    color: "#122234",
    fontSize: 22,
    lineHeight: 28,
    fontWeight: "900",
  },
  cardSubtitle: {
    color: "#5c6d80",
    lineHeight: 20,
    fontSize: 14,
  },
  fieldBlock: {
    gap: 8,
  },
  fieldHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },
  label: {
    color: "#5c6d80",
    fontSize: 12,
    fontWeight: "800",
    letterSpacing: 1,
    textTransform: "uppercase",
  },
  helperBadge: {
    color: "#0c7a4d",
    backgroundColor: "rgba(12,122,77,0.1)",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: "hidden",
    fontSize: 11,
    fontWeight: "800",
  },
  input: {
    backgroundColor: "#f4f7fb",
    borderColor: "rgba(16,39,61,0.12)",
    borderWidth: 1,
    borderRadius: 18,
    paddingHorizontal: 16,
    paddingVertical: 14,
    fontSize: 16,
    color: "#102233",
  },
  multilineInput: {
    minHeight: 140,
  },
  primaryButton: {
    backgroundColor: "#1f537f",
    borderRadius: 18,
    minHeight: 52,
    alignItems: "center",
    justifyContent: "center",
    flexDirection: "row",
    gap: 8,
  },
  primaryButtonText: {
    color: "#ffffff",
    fontSize: 16,
    fontWeight: "800",
  },
  noteCard: {
    marginTop: 14,
    backgroundColor: "rgba(255,255,255,0.08)",
    borderRadius: 22,
    padding: 16,
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
    gap: 6,
  },
  noteTitle: {
    color: "#f5dca7",
    fontSize: 14,
    fontWeight: "800",
  },
  noteText: {
    color: "rgba(255,255,255,0.8)",
    lineHeight: 20,
  },
  shell: {
    flex: 1,
    backgroundColor: "#081320",
  },
  shellContent: {
    paddingHorizontal: 20,
    paddingTop: 18,
    paddingBottom: 30,
    gap: 16,
  },
  shellHeader: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
  },
  brandRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    flex: 1,
  },
  logoMarkSmall: {
    width: 40,
    height: 40,
    borderRadius: 14,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(255,255,255,0.08)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
  },
  brandName: {
    color: "#ffffff",
    fontSize: 17,
    fontWeight: "900",
  },
  brandTag: {
    color: "rgba(255,255,255,0.68)",
    fontSize: 11,
    textTransform: "uppercase",
    letterSpacing: 1.3,
    marginTop: 2,
  },
  signOutButton: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    backgroundColor: "#f5dca7",
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 999,
  },
  signOutText: {
    color: "#0d2133",
    fontWeight: "900",
    fontSize: 12,
  },
  heroCard: {
    borderRadius: 28,
    padding: 18,
    gap: 10,
    backgroundColor: "rgba(255,255,255,0.07)",
    borderWidth: 1,
    borderColor: "rgba(255,255,255,0.12)",
  },
  heroKicker: {
    color: "#d9ae55",
    textTransform: "uppercase",
    letterSpacing: 2,
    fontSize: 11,
    fontWeight: "800",
  },
  heroTitle: {
    color: "#ffffff",
    fontSize: 30,
    lineHeight: 34,
    fontWeight: "900",
  },
  heroBody: {
    color: "rgba(255,255,255,0.78)",
    lineHeight: 21,
  },
  quickGrid: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 10,
  },
  quickCard: {
    minWidth: 100,
    flexGrow: 1,
    backgroundColor: "rgba(255,255,255,0.94)",
    borderRadius: 20,
    padding: 14,
    gap: 8,
    alignItems: "flex-start",
  },
  quickCardActive: {
    borderWidth: 2,
    borderColor: "#1f537f",
  },
  quickLabel: {
    color: "#102233",
    fontWeight: "800",
  },
  moduleCard: {
    backgroundColor: "rgba(255,255,255,0.96)",
    borderRadius: 22,
    padding: 14,
    gap: 8,
  },
  moduleTitle: {
    color: "#102233",
    fontSize: 18,
    fontWeight: "900",
  },
  moduleMeta: {
    color: "#274764",
    fontSize: 13,
    fontWeight: "700",
  },
  moduleBody: {
    color: "#5c6d80",
    lineHeight: 20,
  },
  inlineForm: {
    gap: 10,
  },
  inlineFormActions: {
    flexDirection: "row",
    gap: 10,
    flexWrap: "wrap",
  },
  secondaryButton: {
    backgroundColor: "#1f537f",
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 10,
    alignSelf: "flex-start",
  },
  secondaryButtonText: {
    color: "#ffffff",
    fontSize: 13,
    fontWeight: "800",
  },
  ghostButton: {
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: "rgba(16,34,51,0.12)",
    backgroundColor: "rgba(16,34,51,0.04)",
  },
  ghostButtonText: {
    color: "#102233",
    fontSize: 13,
    fontWeight: "800",
  },
  listStack: {
    gap: 10,
    marginTop: 4,
  },
  listRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    padding: 14,
    borderRadius: 18,
    backgroundColor: "rgba(16,34,51,0.04)",
  },
  flexGrowArea: {
    flex: 1,
  },
  listTitle: {
    color: "#102233",
    fontSize: 15,
    fontWeight: "900",
  },
  listMeta: {
    color: "#5c6d80",
    fontSize: 12,
    marginTop: 2,
  },
  rowActions: {
    flexDirection: "row",
    gap: 8,
    flexWrap: "wrap",
  },
  rowActionButton: {
    backgroundColor: "#d8e2ff",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  rowActionButtonDanger: {
    backgroundColor: "#ffdad6",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  rowActionText: {
    color: "#102233",
    fontSize: 12,
    fontWeight: "800",
  },
  rowActionTextDanger: {
    color: "#8a1f1f",
    fontSize: 12,
    fontWeight: "800",
  },
  sectionBlock: {
    gap: 10,
  },
  sectionTitle: {
    color: "#ffffff",
    fontSize: 18,
    fontWeight: "900",
  },
  articleCard: {
    backgroundColor: "rgba(255,255,255,0.96)",
    borderRadius: 22,
    padding: 14,
    gap: 8,
  },
  articleTopRow: {
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
    gap: 10,
  },
  articleTitle: {
    flex: 1,
    color: "#102233",
    fontSize: 15,
    lineHeight: 20,
    fontWeight: "900",
  },
  articleAudience: {
    color: "#0b5cab",
    fontSize: 11,
    fontWeight: "800",
    textTransform: "uppercase",
    letterSpacing: 0.8,
  },
  articleSnippet: {
    color: "#5c6d80",
    lineHeight: 20,
  },
  articleDetailCard: {
    backgroundColor: "rgba(255,255,255,0.98)",
    borderRadius: 24,
    padding: 16,
    gap: 10,
  },
  articleDetailAudience: {
    color: "#0b5cab",
    fontSize: 12,
    fontWeight: "800",
    textTransform: "uppercase",
    letterSpacing: 0.8,
  },
  articleDetailContent: {
    color: "#35506d",
    lineHeight: 22,
  },
  closeText: {
    color: "#0b5cab",
    fontWeight: "800",
  },
  bootScreen: {
    flex: 1,
    backgroundColor: "#081320",
    alignItems: "center",
    justifyContent: "center",
    padding: 24,
  },
  bootCard: {
    alignItems: "center",
    gap: 12,
  },
  bootTitle: {
    color: "#ffffff",
    fontSize: 24,
    fontWeight: "900",
  },
  bootSubtitle: {
    color: "rgba(255,255,255,0.7)",
  },
});
