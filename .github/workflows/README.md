# GitHub Actions 自动编译配置说明

## 功能说明

此 workflow 会自动编译以下平台的安装包：
- Windows x86_64 (.exe, .msi)
- Linux x86_64 (.deb, .AppImage)
- Linux ARM64 (.deb, .AppImage)

## 触发方式

1. **自动触发**：推送以 `v` 开头的 tag
   ```bash
   git tag v1.0.1
   git push origin v1.0.1
   ```

2. **手动触发**：在 GitHub Actions 页面手动运行 workflow

## 必需的 GitHub Secrets 配置

在仓库的 `Settings` → `Secrets and variables` → `Actions` 中添加以下 secrets：

### 1. TOKEN (必需)
- **用途**：用于创建 GitHub Release 和上传编译产物
- **获取方式**：
  1. 前往 GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
  2. 生成新 token，至少需要 `repo` 权限
  3. 复制 token 并添加到仓库 secrets 中，名称为 `TOKEN`

### 2. TAURI_PRIVATE_KEY (可选，用于自动更新)
- **用途**：用于签名应用更新
- **生成方式**：
  ```bash
  npm run tauri signer generate -- -w ~/.tauri/myapp.key
  ```
- 将生成的私钥内容添加到 secrets 中

### 3. TAURI_KEY_PASSWORD (可选，与私钥配套)
- **用途**：私钥的密码
- 如果生成私钥时设置了密码，需要将密码添加到此 secret

## 使用步骤

1. 配置好上述 secrets
2. 更新版本号（在 `package.json`, `src-tauri/Cargo.toml`, `src-tauri/tauri.conf.json` 中）
3. 提交代码
4. 打 tag 并推送：
   ```bash
   git tag v1.0.1
   git push origin v1.0.1
   ```
5. GitHub Actions 会自动开始编译
6. 编译完成后，会在 Releases 页面自动创建新版本并上传安装包

## 注意事项

- ARM64 Linux 编译是交叉编译，可能需要较长时间
- 如果不需要某个平台，可以在 `release.yml` 中删除对应的 matrix 配置
- 编译产物会自动上传到对应 tag 的 Release 中

