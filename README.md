# SWCS - Syu Web Check System

## 01. システム位置づけ

SWCS - Syu Web Check System は、SEEN - Studio-crs Evolve Ecosystem Network におけるWeb確認用Satellite Engineである。

SWCSは、SADS - Syu AI Diagnosis System に進む前段階として、対象Webサイトの取得状態・構造・情報量・導線を確認する。

SWCSは、診断・採点・評価を行わない。
役割は、対象Webサイトの現状を取得・確認・可視化することである。

---

## 02. SWCS Client Report Sheet Ver.1.0

SWCS Client Report Sheet Ver.1.0 は、クライアント提示用のWeb確認レポートである。

対象Webサイトの取得状態、構造、確認領域、キーワード候補、リンク・メディア・導線、Studio-crsコメント、次工程への流れを、PDFとして提示できる形に整備した。

---

## 03. 完成した内容

SWCS Client Report Sheet Ver.1.0 として、以下の内容が完成した。

* クライアント提示用PDFレポート画面
* `Public/report.php`
* レポート確認用の基準JSON
* 7ページ構成のPDFレイアウト
* Executive View
* SWCS Check Coverage Map
* Current Site Coverage
* SWCS Standard Coverage Model
* Check Coverage
* Detailed Findings
* Keyword Candidates
* Studio-crs Comment
* Next Step Flow

---

## 04. レポートURL例

ローカル環境でのレポート確認URL例：

```text
http://localhost:8001/report.php?file=life_escortist_swcs_output_v2.json&client=Life%20Escortist&staff=Syuji%20Konishi
```

---

## 05. PDF保存時の注意

ブラウザの印刷画面からPDF保存する場合は、ヘッダーとフッターをOFFにする。

推奨設定：

```text
ヘッダーとフッター：OFF
```

これにより、ブラウザ側で自動表示される日時、ページ番号、localhost情報がPDFに入ることを防ぐ。

---

## 06. SEEN内での流れ

SWCSは、SEEN営業導線において最初のWeb確認工程を担当する。

```text
SWCS Web Check
↓
Client Confirmation
↓
SADS AI Diagnosis
↓
SAIS Introduction Proposal
↓
SASS Operation / Advisory
```

---

## 07. SADSへの引き継ぎ前提

SWCSは、対象Webサイトへアクセスできるか、どのような情報が取得できるかを確認する。

SWCSで取得・確認したWebサイトの現状は、SADSへ進むための前提情報となる。

SADSでは、そのSWCS出力をもとにAI診断・採点・レポート生成を行う。

---

## 08. SWCSの責務

SWCSの責務は以下である。

1. 対象Webサイトへアクセスする
2. HTMLを取得する
3. 取得状態を確認する
4. 構造情報を整理する
5. キーワード候補を抽出する
6. リンク・メディア・導線を確認する
7. クライアントに提示できる形で可視化する

---

## 09. SWCSの境界

SWCSは以下を行わない。

1. AI診断
2. 採点
3. 評価
4. 改善提案の決定
5. 導入見積
6. 運用設計

これらは、後続工程であるSADS、SAIS、SASSが担当する。

---

## 10. 現在の状態

```text
SWCS Client Report Sheet Ver.1.0 完成
GitHubバックアップ完了
終業時 clean 状態確認済み
```

---

## 11. 完了日

2026年08月01日
SWCS Client Report Sheet Ver.1.0 完成
