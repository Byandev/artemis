# CSR Leaderboards

<h3>Backend Updates</h3>
<ul>
<li>Initial Uploading</li>
    <ol>
        <li>Tokens must be used in the API to get the data in the database.</li>
    </ol>
<li>Refinement Upload</li>
    <ol>
        <li>API now use public endpoints to access the data, making it accessible to guests.</li>
    </ol>
</ul>

<h3>API Testing Snapshots</h3>
<a href="https://drive.google.com/file/d/1OydeP9v4-3gu3P658eVyzr-87zGFOXOr/view?usp=drive_link">Daily CSR Leaderboards</a><br>
<a href="https://drive.google.com/file/d/1zvMCUdkb6InqlyQszmGR462QkQsUl7l_/view?usp=drive_link">Weekly CSR Leaderboards</a><br>
<a href="https://drive.google.com/file/d/1ke2Aj658R4vzbIiyALqxEHXheh3n1Aab/view?usp=drive_link">Monthly CSR Leaderboards</a>

<h3>Finished CSR Leaderboards</h3>
<b>http://localhost/csr-leaderboards/index.html</b><br>

<h3>Updates / Patches </h3>
<ul>
    <li>Added public API endpoints for CSR performance and CSR list, with rate limiting to prevent abuse.</li>
    <li>Added throttle middleware to limit the number of requests a client can make.</li>
    <li>Added DoS protection to prevent abuse of the public API endpoints.</li>
</ul>
