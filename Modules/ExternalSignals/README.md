# SWCS External Signals Module

SWCS External Signals Module は、SWCS本体のWeb巡回結果に、外部サービスの実測値を接続するための拡張モジュールです。

## Purpose

SWCS本体は、Studio-crs独自のWeb巡回・構造確認・リンク確認・導線確認を担当します。

Google Search Console、Bing Webmaster Tools、Google Business Profile などの外部サービス接続は、SWCS本体へ直接混在させず、Modules/ExternalSignals に分離して管理します。

## Scope

- Google Search Console Signals
- Bing Webmaster Tools Signals
- Google Business Profile Signals
- External Signal Aggregation
- SWCS Report Integration
- Future SADS Bridge

## Directory

```text
Modules/ExternalSignals/
├── SearchConsole/
│   ├── Google/
│   └── Bing/
├── BusinessProfile/
│   └── Google/
├── Aggregation/
├── Schema/
├── Samples/
└── README.md

cd ~/Desktop/SWCS

mkdir -p Modules/ExternalSignals/SearchConsole/Google
mkdir -p Modules/ExternalSignals/SearchConsole/Bing
mkdir -p Modules/ExternalSignals/BusinessProfile/Google
mkdir -p Modules/ExternalSignals/Aggregation
mkdir -p Modules/ExternalSignals/Schema
mkdir -p Modules/ExternalSignals/Samples

cat > Modules/ExternalSignals/README.md <<'EOF'
# SWCS External Signals Module

SWCS External Signals Module は、SWCS本体のWeb巡回結果に、外部サービスの実測値を接続するための拡張モジュールです。

## Purpose

SWCS本体は、Studio-crs独自のWeb巡回・構造確認・リンク確認・導線確認を担当します。

Google Search Console、Bing Webmaster Tools、Google Business Profile などの外部サービス接続は、SWCS本体へ直接混在させず、Modules/ExternalSignals に分離して管理します。

## Scope

- Google Search Console Signals
- Bing Webmaster Tools Signals
- Google Business Profile Signals
- External Signal Aggregation
- SWCS Report Integration
- Future SADS Bridge

## Directory

Modules/ExternalSignals/
├── SearchConsole/
│   ├── Google/
│   └── Bing/
├── BusinessProfile/
│   └── Google/
├── Aggregation/
├── Schema/
├── Samples/
└── README.md

## Initial Policy

初期実装ではAPI自動接続を行わず、手動JSON入力から開始します。

Manual JSON Input
↓
ExternalSignalAggregator
↓
SWCS Output JSON
↓
SWCS Report PDF
↓
SADS Diagnosis Input

## Boundary

External Signals Module は、外部実測値の受け取り・整形・SWCS JSONへの統合・Report表示補助を担当します。

AI診断、スコアリング、改善提案、導入提案は行いません。これらはSADSおよびSAISの責務です。

## Version

Specification: SWCS External Signals Module Specification Ver.1.0
Created: 2026-08-03
